<?php

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Enums\AiModelPurpose;
use App\Enums\AiProviderProtocol;
use App\Services\Ai\ModelSystemContext;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\AiRuntime\LenientHistoryTrimmer;
use App\Services\Reception\ReceptionAgentFactory;
use App\Services\Reception\ReceptionPreemptionSignal;
use App\Services\Reception\ReceptionTurnExecutor;
use App\Services\Reception\ReceptionTurnRunner;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\AgentHandler;
use NeuronAI\Chat\History\InMemoryChatHistory;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\Stream\Adapters\StreamAdapterInterface;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Workflow\Interrupt\InterruptRequest;

/**
 * 创建记录最新输入且不产生模型事件的测试 Agent。
 */
function receptionTurnRecordingAgent(): Agent
{
    $agent = new class extends Agent
    {
        public Message|array $receivedMessages = [];

        /**
         * 记录本轮最新消息并返回空事件流。
         */
        public function stream(Message|array $messages = [], ?InterruptRequest $interrupt = null): AgentHandler
        {
            $this->receivedMessages = $messages;

            return new class($this) extends AgentHandler
            {
                /**
                 * 返回不产生模型事件的测试流。
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

test('执行器把联系人历史与当前会话角色消息按顺序交给 OpenAI 协议模型', function () {
    $agent = receptionTurnRecordingAgent();
    $factory = Mockery::mock(ReceptionAgentFactory::class);
    $factory->shouldReceive('make')
        ->once()
        ->with(Mockery::type(RuntimeModelCandidateData::class), '接待系统指令', [])
        ->andReturn($agent);

    $executor = new ReceptionTurnExecutor(
        $factory,
        new ReceptionTurnRunner(new ReceptionPreemptionSignal),
        new ModelSystemContext,
    );
    $latest = new UserMessage('当前问题');

    $executor->execute(
        new RuntimeModelCandidateData(
            protocol: AiProviderProtocol::OpenAI,
            credentials: ['key' => 'test'],
            model_id: 'test-model',
            supports_image_input: false,
            supports_video_input: false,
        ),
        '接待系统指令',
        [],
        [new UserMessage('本会话旧问题'), new AssistantMessage('本会话旧回答')],
        "以下是联系人历史背景：\n<contact-history>\n[访客] 上次咨询退款\n</contact-history>",
        $latest,
        'conversation-id',
        'turn-id',
        new AiUsageContext(purpose: AiModelPurpose::ReceptionChat),
    );

    $history = $agent->getChatHistory()->getMessages();

    expect(array_map(static fn (Message $message): string => $message->getRole(), $history))
        ->toBe(['system', 'user', 'assistant'])
        ->and($history[0]->getContent())->toContain('<contact-history>')
        ->and($history[1]->getContent())->toBe('本会话旧问题')
        ->and($history[2]->getContent())->toBe('本会话旧回答')
        ->and($agent->receivedMessages)->toBe($latest);
});

test('执行器把联系人历史并入非 OpenAI 协议的系统提示词', function (AiProviderProtocol $protocol) {
    $agent = receptionTurnRecordingAgent();
    $contactHistory = "以下是联系人历史背景：\n<contact-history>\n[访客] 上次咨询退款\n</contact-history>";
    $factory = Mockery::mock(ReceptionAgentFactory::class);
    $factory->shouldReceive('make')
        ->once()
        ->with(
            Mockery::type(RuntimeModelCandidateData::class),
            "接待系统指令\n\n{$contactHistory}",
            [],
        )
        ->andReturn($agent);

    $executor = new ReceptionTurnExecutor(
        $factory,
        new ReceptionTurnRunner(new ReceptionPreemptionSignal),
        new ModelSystemContext,
    );
    $latest = new UserMessage('当前问题');

    $executor->execute(
        new RuntimeModelCandidateData(
            protocol: $protocol,
            credentials: ['key' => 'test'],
            model_id: 'test-model',
            supports_image_input: false,
            supports_video_input: false,
        ),
        '接待系统指令',
        [],
        [new UserMessage('本会话旧问题'), new AssistantMessage('本会话旧回答')],
        $contactHistory,
        $latest,
        'conversation-id',
        'turn-id',
        new AiUsageContext(purpose: AiModelPurpose::ReceptionChat),
    );

    $history = $agent->getChatHistory()->getMessages();

    expect(array_map(static fn (Message $message): string => $message->getRole(), $history))
        ->toBe(['user', 'assistant'])
        ->and($history[0]->getContent())->toBe('本会话旧问题')
        ->and($history[1]->getContent())->toBe('本会话旧回答')
        ->and($agent->receivedMessages)->toBe($latest);
})->with([
    AiProviderProtocol::Anthropic,
    AiProviderProtocol::Gemini,
]);
