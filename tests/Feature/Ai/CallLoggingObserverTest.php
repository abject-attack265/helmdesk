<?php

use App\Enums\AiCallPurpose;
use App\Enums\AiModelPurpose;
use App\Models\AiCallLog;
use App\Services\Ai\Logging\CallLoggingObserver;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\AiRuntime\LenientHistoryTrimmer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\History\InMemoryChatHistory;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\Events\InferenceStart;
use NeuronAI\Observability\Events\InferenceStop;
use NeuronAI\Observability\Events\ToolCalled;
use NeuronAI\Tools\Tool;

uses(RefreshDatabase::class);

/**
 * 构造允许 SYSTEM 历史消息的测试 agent。
 */
function agentWith(string $instructions, array $tools = []): Agent
{
    $agent = Agent::make()->setChatHistory(new InMemoryChatHistory(trimmer: new LenientHistoryTrimmer));
    $agent->setInstructions($instructions);
    if ($tools !== []) {
        $agent->addTool($tools);
    }

    return $agent;
}

test('一次运行落成 user + assistant 条目，含 token、system prompt 与工具快照', function () {
    $turnId = (string) Str::uuid();
    $agent = agentWith('你是接待助手', [Tool::make('knowledge_search', '检索知识库')]);
    $conversationId = (string) Str::ulid();
    $messageIds = [(string) Str::ulid()];

    $observer = new CallLoggingObserver(new AiUsageContext(
        purpose: AiModelPurpose::ReceptionChat,
        aiModelId: (string) Str::ulid(),
        modelName: 'gpt-4o',
        conversationId: $conversationId,
        callPurpose: AiCallPurpose::ReceptionReply,
        turnId: $turnId,
        conversationMessageIds: $messageIds,
    ), $agent);

    $observer->onEvent('inference-start', new stdClass, new InferenceStart(new UserMessage('你们几点关门？')));
    $response = (new AssistantMessage('我们晚上十点关门'))->setUsage(new Usage(120, 30));
    $observer->onEvent('inference-stop', new stdClass, new InferenceStop(false, $response));

    expect(AiCallLog::query()->count())->toBe(1);

    $log = AiCallLog::query()->first();
    expect($log->purpose)->toBe(AiCallPurpose::ReceptionReply)
        ->and($log->conversation_id)->toBe($conversationId)
        ->and($log->status)->toBe('success')
        ->and($log->input_tokens)->toBe(120)
        ->and($log->output_tokens)->toBe(30)
        ->and($log->turn_count)->toBe(1)
        ->and($log->model_name)->toBe('gpt-4o')
        ->and($log->system_prompts)->toBe(['你是接待助手'])
        ->and($log->available_tools[0]['name'] ?? null)->toBe('knowledge_search')
        ->and($log->reply_preview)->toBe('我们晚上十点关门');

    // 时间线：user 输入（带回链的会话消息 ID）+ assistant 回复（text 分段 + 本轮 token）
    [$user, $assistant] = $log->messages;
    expect($user['role'])->toBe('user')
        ->and($user['content'])->toBe('你们几点关门？')
        ->and($user['turn_id'])->toBe($turnId)
        ->and($user['conversation_message_ids'])->toBe($messageIds)
        ->and($assistant['role'])->toBe('assistant')
        ->and($assistant['segments'])->toBe([['type' => 'text', 'content' => '我们晚上十点关门']])
        ->and($assistant['input_tokens'])->toBe(120)
        ->and($assistant['output_tokens'])->toBe(30)
        ->and($assistant['model_name'])->toBe('gpt-4o');
});

test('URL 图片写入 user 条目的媒体快照', function () {
    $agent = agentWith('你是图片助手');
    $observer = new CallLoggingObserver(new AiUsageContext(
        purpose: AiModelPurpose::Assistant,
        modelName: 'gpt-4o',
        callPurpose: AiCallPurpose::Assistant,
    ), $agent);
    $imageUrl = 'https://cdn.example.com/messages/photo.png';

    $observer->onEvent('inference-start', new stdClass, new InferenceStart(new UserMessage([
        new TextContent('这是什么？'),
        new ImageContent($imageUrl, SourceType::URL, 'image/png'),
    ])));
    $response = (new AssistantMessage('这是一张图片'))->setUsage(new Usage(20, 5));
    $observer->onEvent('inference-stop', new stdClass, new InferenceStop(false, $response));

    $user = AiCallLog::query()->firstOrFail()->messages[0];

    expect($user['media'])->toBe([[
        'type' => 'image',
        'url' => $imageUrl,
    ]]);
});

test('react 多轮 inference 累进同一条 assistant 的分段，token 汇总', function () {
    $agent = agentWith('系统提示');
    $observer = new CallLoggingObserver(new AiUsageContext(
        purpose: AiModelPurpose::ReceptionChat,
        callPurpose: AiCallPurpose::ReceptionReply,
        conversationId: (string) Str::ulid(),
        turnId: (string) Str::uuid(),
    ), $agent);

    $observer->onEvent('inference-start', new stdClass, new InferenceStart(new UserMessage('hi')));
    $observer->onEvent('inference-stop', new stdClass, new InferenceStop(false, (new AssistantMessage('查一下'))->setUsage(new Usage(10, 5))));
    $observer->onEvent('inference-start', new stdClass, new InferenceStart(new UserMessage('tool result')));
    $observer->onEvent('inference-stop', new stdClass, new InferenceStop(false, (new AssistantMessage('查到了'))->setUsage(new Usage(10, 5))));

    expect(AiCallLog::query()->count())->toBe(1);

    $log = AiCallLog::query()->first();
    $assistant = collect($log->messages)->firstWhere('role', 'assistant');
    expect($log->input_tokens)->toBe(20)
        ->and($log->output_tokens)->toBe(10)
        ->and($assistant['segments'])->toBe([
            ['type' => 'text', 'content' => '查一下'],
            ['type' => 'text', 'content' => '查到了'],
        ])
        // 后续轮的 tool result 伪 user 消息不重复生成 user 条目
        ->and(collect($log->messages)->where('role', 'user')->count())->toBe(1);
});

test('工具调用按事件顺序内嵌为 tool_call 分段（含 respond）', function () {
    $agent = agentWith('系统提示');
    $observer = new CallLoggingObserver(new AiUsageContext(
        purpose: AiModelPurpose::ReceptionChat,
        callPurpose: AiCallPurpose::ReceptionReply,
        conversationId: (string) Str::ulid(),
        turnId: (string) Str::uuid(),
    ), $agent);

    $respond = Tool::make('respond', '发送给访客')
        ->setInputs(['message' => '晚上十点关门'])
        ->setResult(['ok' => true]);

    $observer->onEvent('inference-start', new stdClass, new InferenceStart(new UserMessage('几点关门')));
    $observer->onEvent('inference-stop', new stdClass, new InferenceStop(false, new ToolCallMessage(null, [$respond])));
    $observer->onEvent('tool-called', new stdClass, new ToolCalled($respond));

    $log = AiCallLog::query()->firstOrFail();
    $assistant = collect($log->messages)->firstWhere('role', 'assistant');
    $toolSegments = array_values(array_filter($assistant['segments'], fn (array $s) => $s['type'] === 'tool_call'));
    expect($toolSegments)->toHaveCount(1)
        ->and($toolSegments[0]['name'])->toBe('respond')
        ->and($toolSegments[0]['inputs'])->toBe(['message' => '晚上十点关门'])
        ->and($toolSegments[0]['result'])->toBe('{"ok":true}');
});

test('接待同一会话的多个 turn 追加进同一行，其他会话不混入', function () {
    $conversationId = (string) Str::ulid();

    foreach (['第一轮', '第二轮'] as $i => $text) {
        $observer = new CallLoggingObserver(new AiUsageContext(
            purpose: AiModelPurpose::ReceptionChat,
            modelName: 'gpt-4o',
            conversationId: $conversationId,
            callPurpose: AiCallPurpose::ReceptionReply,
            turnId: (string) Str::uuid(),
        ), agentWith('系统提示'));
        $observer->onEvent('inference-start', new stdClass, new InferenceStart(new UserMessage($text)));
        $observer->onEvent('inference-stop', new stdClass, new InferenceStop(false, (new AssistantMessage('回复'.$text))->setUsage(new Usage(10, 5))));
    }

    // 另一个会话独立成行
    $other = new CallLoggingObserver(new AiUsageContext(
        purpose: AiModelPurpose::ReceptionChat,
        conversationId: (string) Str::ulid(),
        callPurpose: AiCallPurpose::ReceptionReply,
        turnId: (string) Str::uuid(),
    ), agentWith('系统提示'));
    $other->onEvent('inference-start', new stdClass, new InferenceStart(new UserMessage('别的会话')));
    $other->onEvent('inference-stop', new stdClass, new InferenceStop(false, new AssistantMessage('好的')));

    expect(AiCallLog::query()->count())->toBe(2);

    $log = AiCallLog::query()->where('conversation_id', $conversationId)->firstOrFail();
    expect($log->messages)->toHaveCount(4)
        ->and($log->turn_count)->toBe(2)
        ->and($log->input_tokens)->toBe(20)
        ->and($log->reply_preview)->toBe('回复第二轮');
});

test('error 事件把 assistant 条目标为失败并把整行标为 error', function () {
    $agent = agentWith('系统提示');
    $observer = new CallLoggingObserver(new AiUsageContext(
        purpose: AiModelPurpose::BackgroundTask,
        callPurpose: AiCallPurpose::ConversationSummary,
    ), $agent);

    $observer->onEvent('inference-start', new stdClass, new InferenceStart(new UserMessage('hi')));
    $observer->onEvent('error', new stdClass, new AgentError(new RuntimeException('上游 429 限流')));

    $log = AiCallLog::query()->first();
    $assistant = collect($log->messages)->firstWhere('role', 'assistant');
    expect($log->status)->toBe('error')
        ->and($log->error_message)->toBe('上游 429 限流')
        ->and($assistant['status'])->toBe('error')
        ->and($assistant['error_message'])->toBe('上游 429 限流');
});

test('接待模型降级重试时同轮 user 条目不重复，失败与成功的 assistant 都保留', function () {
    $conversationId = (string) Str::ulid();
    $turnId = (string) Str::uuid();

    $context = fn (string $model): AiUsageContext => new AiUsageContext(
        purpose: AiModelPurpose::ReceptionChat,
        modelName: $model,
        conversationId: $conversationId,
        callPurpose: AiCallPurpose::ReceptionReply,
        turnId: $turnId,
    );

    // 第一个候选失败
    $failed = new CallLoggingObserver($context('gpt-4o'), agentWith('系统提示'));
    $failed->onEvent('inference-start', new stdClass, new InferenceStart(new UserMessage('几点关门')));
    $failed->onEvent('error', new stdClass, new AgentError(new RuntimeException('超时')));

    // 降级候选成功
    $ok = new CallLoggingObserver($context('deepseek'), agentWith('系统提示'));
    $ok->onEvent('inference-start', new stdClass, new InferenceStart(new UserMessage('几点关门')));
    $ok->onEvent('inference-stop', new stdClass, new InferenceStop(false, new AssistantMessage('晚上十点')));

    $log = AiCallLog::query()->firstOrFail();
    $roles = array_column($log->messages, 'role');
    expect($roles)->toBe(['user', 'assistant', 'assistant'])
        // 任一条目失败即整行 error（保留失败痕迹供排查）
        ->and($log->status)->toBe('error')
        ->and($log->model_name)->toBe('deepseek')
        ->and($log->reply_preview)->toBe('晚上十点');
});

test('OpenAI 协议下作为 SYSTEM 消息注入的联系人历史背景进入 system_prompts 快照', function () {
    $agent = agentWith('你是接待助手');
    $agent->getChatHistory()->addMessage(new Message(MessageRole::SYSTEM, '以下是联系人历史会话：[访客] 你好'));

    $observer = new CallLoggingObserver(new AiUsageContext(
        purpose: AiModelPurpose::ReceptionChat,
        callPurpose: AiCallPurpose::ReceptionReply,
        conversationId: (string) Str::ulid(),
        turnId: (string) Str::uuid(),
    ), $agent);

    $observer->onEvent('inference-start', new stdClass, new InferenceStart(new UserMessage('在吗')));
    $observer->onEvent('inference-stop', new stdClass, new InferenceStop(false, new AssistantMessage('在的')));

    expect(AiCallLog::query()->first()->system_prompts)->toBe([
        '你是接待助手',
        '以下是联系人历史会话：[访客] 你好',
    ]);
});

test('无配对 inference-start 的 stop 不落库', function () {
    $observer = new CallLoggingObserver(new AiUsageContext(
        purpose: AiModelPurpose::Assistant,
        callPurpose: AiCallPurpose::Assistant,
    ), agentWith('系统提示'));

    $observer->onEvent('inference-stop', new stdClass, new InferenceStop(false, new AssistantMessage('ok')));

    expect(AiCallLog::query()->count())->toBe(0);
});
