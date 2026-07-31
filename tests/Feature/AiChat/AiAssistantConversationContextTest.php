<?php

use App\Actions\AiChat\BuildAiAssistantConversationContextAction;
use App\Actions\AiChat\FinalizeAiAssistantMessageAction;
use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Enums\AiAssistantMessageStatus;
use App\Enums\AiProviderProtocol;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Events\AiChat\AiChatStreamChunk;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\AiChat\AiChatStreamRunner;
use App\Services\AiChat\AiChatStreamSignal;
use App\Services\AiChat\CalculatorToolFactory;
use App\Services\AiRuntime\LenientHistoryTrimmer;
use App\Services\Reception\ReceptionAgentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\AgentHandler;
use NeuronAI\Chat\History\InMemoryChatHistory;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\Stream\Adapters\StreamAdapterInterface;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Tools\Tool;
use NeuronAI\Workflow\Interrupt\InterruptRequest;

uses(RefreshDatabase::class);

/**
 * 创建记录本轮输入且不产生模型事件的助手测试 Agent。
 */
function aiAssistantContextRecordingAgent(): Agent
{
    $agent = new class extends Agent
    {
        public Message|array $receivedMessages = [];

        /**
         * 记录本轮输入并返回空事件流。
         */
        public function stream(Message|array $messages = [], ?InterruptRequest $interrupt = null): AgentHandler
        {
            $this->receivedMessages = $messages;

            return new class($this) extends AgentHandler
            {
                /**
                 * 返回不产生模型事件的测试事件流。
                 */
                public function events(?StreamAdapterInterface $adapter = null): Generator
                {
                    yield from [];
                }
            };
        }
    };
    $agent->setChatHistory(new InMemoryChatHistory(trimmer: new LenientHistoryTrimmer));

    return $agent;
}

/**
 * 创建发送文本片段后抛出可重试错误的助手测试 Agent。
 */
function aiAssistantContextInterruptedAgent(): Agent
{
    $agent = new class extends Agent
    {
        /**
         * 返回先产生文本再中断的事件流。
         */
        public function stream(Message|array $messages = [], ?InterruptRequest $interrupt = null): AgentHandler
        {
            return new class($this) extends AgentHandler
            {
                /**
                 * 发送可见片段后模拟上游服务失败。
                 */
                public function events(?StreamAdapterInterface $adapter = null): Generator
                {
                    yield new TextChunk('model-id', '部分回答');

                    throw new RuntimeException('503 service unavailable');
                }
            };
        }
    };
    $agent->setChatHistory(new InMemoryChatHistory(trimmer: new LenientHistoryTrimmer));

    return $agent;
}

test('当前客户会话角色和附件访问地址进入助手背景', function () {
    fakeAttachmentStorage();
    $contact = Contact::factory()->visitor()->create([
        'ai_context' => [
            'handoff_brief' => [
                'brief' => '访客的退款尚未到账。',
                'next_actions' => ['核对退款流水'],
                'source_locale' => 'zh-CN',
                'translations' => [],
                'updated_at' => now()->toIso8601String(),
            ],
        ],
    ]);
    $conversation = Conversation::factory()->forContact($contact)->create([
        'subject' => '退款进度',
        'summary' => '访客正在查询退款审核状态。',
    ]);
    $visitor = ConversationMessage::factory()->visitorText()->forConversation($conversation)->create([
        'content' => '请帮我看看退款为什么还没到账',
    ]);
    ConversationMessage::factory()->aiText()->forConversation($conversation)->create([
        'content' => '请提供订单号',
    ]);
    ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => '订单号已经收到',
    ]);
    $image = Attachment::factory()->create([
        'attachable_type' => $visitor->getMorphClass(),
        'attachable_id' => $visitor->id,
        'original_name' => 'refund.png',
        'mime_type' => 'image/png',
    ]);
    $video = Attachment::factory()->create([
        'attachable_type' => $visitor->getMorphClass(),
        'attachable_id' => $visitor->id,
        'original_name' => 'screen.mp4',
        'mime_type' => 'video/mp4',
    ]);
    $file = Attachment::factory()->create([
        'attachable_type' => $visitor->getMorphClass(),
        'attachable_id' => $visitor->id,
        'original_name' => 'order.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $context = app(BuildAiAssistantConversationContextAction::class)->handle($conversation);

    expect($context->context)
        ->toContain('退款进度')
        ->toContain('访客正在查询退款审核状态。')
        ->toContain('访客的退款尚未到账。')
        ->toContain('核对退款流水')
        ->toContain('请帮我看看退款为什么还没到账')
        ->toContain('"role":"访客"')
        ->toContain('请提供订单号')
        ->toContain('订单号已经收到')
        ->toContain('"role":"人工客服"')
        ->toContain('[图片：refund.png；链接：')
        ->toContain($image->full_url)
        ->toContain('[视频：screen.mp4；链接：')
        ->toContain($video->full_url)
        ->toContain('[文件：order.pdf；链接：')
        ->toContain($file->full_url);
});

test('无媒体时按候选顺序尝试并在可重试错误后切换模型', function () {
    Event::fake([AiChatStreamChunk::class]);
    $conversation = Conversation::factory()->create();
    $agent = aiAssistantContextRecordingAgent();
    $attemptedModelIds = [];
    $agentFactory = Mockery::mock(ReceptionAgentFactory::class);
    $agentFactory->shouldReceive('make')
        ->twice()
        ->andReturnUsing(function (RuntimeModelCandidateData $candidate) use (&$attemptedModelIds, $agent): Agent {
            $attemptedModelIds[] = $candidate->ai_model_id;
            if (count($attemptedModelIds) === 1) {
                throw new RuntimeException('503 service unavailable');
            }

            return $agent;
        });
    $finalize = Mockery::mock(FinalizeAiAssistantMessageAction::class);
    $finalize->shouldReceive('handle')
        ->once()
        ->with('assistant-message-id', AiAssistantMessageStatus::Completed, []);
    $signal = Mockery::mock(AiChatStreamSignal::class);
    $signal->shouldReceive('clear')->once();
    $calculatorTools = Mockery::mock(CalculatorToolFactory::class);
    $calculatorTools->shouldReceive('build')
        ->twice()
        ->andReturn(Tool::make('calculator', '测试计算器'));
    $this->app->instance(ReceptionAgentFactory::class, $agentFactory);
    $this->app->instance(FinalizeAiAssistantMessageAction::class, $finalize);
    $this->app->instance(AiChatStreamSignal::class, $signal);
    $this->app->instance(CalculatorToolFactory::class, $calculatorTools);

    app(AiChatStreamRunner::class)->run(
        roundId: 'round-id',
        modelCandidates: [
            new RuntimeModelCandidateData(
                protocol: AiProviderProtocol::OpenAI,
                credentials: ['key' => 'test'],
                model_id: 'primary-model',
                supports_image_input: false,
                supports_video_input: false,
                ai_model_id: 'primary-model',
            ),
            new RuntimeModelCandidateData(
                protocol: AiProviderProtocol::OpenAI,
                credentials: ['key' => 'test'],
                model_id: 'fallback-model',
                supports_image_input: true,
                supports_video_input: true,
                ai_model_id: 'fallback-model',
            ),
        ],
        messages: [['role' => 'user', 'content' => '概括客户的问题']],
        knowledgeBases: [],
        integrationToolSources: [],
        systemName: 'HelmDesk',
        userTimezone: 'UTC',
        conversationId: $conversation->id,
        threadId: 'thread-id',
        assistantMessageId: 'assistant-message-id',
    );

    expect($attemptedModelIds)->toBe(['primary-model', 'fallback-model']);
});

test('流式片段广播后发生模型错误时结束本轮', function () {
    Event::fake([AiChatStreamChunk::class]);
    $conversation = Conversation::factory()->create();
    $agentFactory = Mockery::mock(ReceptionAgentFactory::class);
    $agentFactory->shouldReceive('make')->once()->andReturn(aiAssistantContextInterruptedAgent());
    $finalize = Mockery::mock(FinalizeAiAssistantMessageAction::class);
    $finalize->shouldReceive('handle')
        ->once()
        ->with(
            'assistant-message-id',
            AiAssistantMessageStatus::Failed,
            [['type' => 'text', 'content' => '部分回答']],
        );
    $signal = Mockery::mock(AiChatStreamSignal::class);
    $signal->shouldReceive('isCancelled')->once()->andReturnFalse();
    $signal->shouldReceive('clear')->once();
    $calculatorTools = Mockery::mock(CalculatorToolFactory::class);
    $calculatorTools->shouldReceive('build')
        ->once()
        ->andReturn(Tool::make('calculator', '测试计算器'));
    $this->app->instance(ReceptionAgentFactory::class, $agentFactory);
    $this->app->instance(FinalizeAiAssistantMessageAction::class, $finalize);
    $this->app->instance(AiChatStreamSignal::class, $signal);
    $this->app->instance(CalculatorToolFactory::class, $calculatorTools);

    app(AiChatStreamRunner::class)->run(
        roundId: 'round-id',
        modelCandidates: [
            new RuntimeModelCandidateData(
                protocol: AiProviderProtocol::OpenAI,
                credentials: ['key' => 'test'],
                model_id: 'primary-model',
                supports_image_input: false,
                supports_video_input: false,
                ai_model_id: 'primary-model',
            ),
            new RuntimeModelCandidateData(
                protocol: AiProviderProtocol::OpenAI,
                credentials: ['key' => 'test'],
                model_id: 'fallback-model',
                supports_image_input: false,
                supports_video_input: false,
                ai_model_id: 'fallback-model',
            ),
        ],
        messages: [['role' => 'user', 'content' => '生成回复']],
        knowledgeBases: [],
        integrationToolSources: [],
        systemName: 'HelmDesk',
        userTimezone: 'UTC',
        conversationId: $conversation->id,
        threadId: 'thread-id',
        assistantMessageId: 'assistant-message-id',
    );

    Event::assertDispatched(
        AiChatStreamChunk::class,
        static fn (AiChatStreamChunk $event): bool => $event->payload === [
            'type' => 'delta',
            'content' => '部分回答',
        ],
    );
});
