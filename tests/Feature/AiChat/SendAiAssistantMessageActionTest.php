<?php

use App\Actions\AiChat\SendAiAssistantMessageAction;
use App\Enums\AiModelPurpose;
use App\Enums\IntegrationTransport;
use App\Jobs\AiChat\RunAiChatStreamJob;
use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\Integration;
use App\Models\IntegrationTool;
use App\Models\KnowledgeBase;
use App\Models\User;
use App\Services\Realtime\MercureTopics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * 创建一个可供 AI 助手运行时模型池选择的 assistant 用途模型。
 */
function seedAiAssistantModel(): AiModel
{
    return makeAiModel(AiModelPurpose::Assistant);
}

/**
 * 在测试会话中发送一轮 AI 助手消息。
 *
 * @param  array<int, array{role: string, content: string, attachment_ids?: list<string>}>  $history
 * @param  list<string>  $attachmentIds
 */
function sendAiAssistantMessageForTest(
    string $prompt,
    string $roundId,
    array $history = [],
    array $attachmentIds = [],
): array {
    return app(SendAiAssistantMessageAction::class)->handle(
        Conversation::factory()->create(),
        User::factory()->create(),
        $prompt,
        $roundId,
        $history,
        $attachmentIds,
    );
}

test('它携带最近二十条历史消息派发到流式 job', function () {
    Bus::fake();

    $app = createSystemSettings();
    $model = seedAiAssistantModel();

    $history = collect(range(1, 25))
        ->map(fn (int $index): array => [
            'role' => $index % 2 === 0 ? 'assistant' : 'user',
            'content' => str_repeat((string) ($index % 10), 8100),
        ])
        ->all();
    $prompt = str_repeat('p', 9000);

    sendAiAssistantMessageForTest($prompt, Str::uuid()->toString(), $history);

    Bus::assertDispatched(RunAiChatStreamJob::class, function (RunAiChatStreamJob $job) use ($history, $prompt, $model): bool {
        $messages = $job->messages;

        return count($messages) === 21
            && $messages[0]['role'] === $history[5]['role']
            && $messages[0]['content'] === $history[5]['content']
            && mb_strlen($messages[0]['content']) === 8100
            && $messages[20]['role'] === 'user'
            && $messages[20]['content'] === $prompt
            && $job->modelCandidates[0]->model_id === $model->model_id;
    });
});

test('它把 assistant 用途的完整加权候选列表传给流式 job', function () {
    Bus::fake();
    createSystemSettings();
    $first = makeAiModel(AiModelPurpose::Assistant);
    $second = makeAiModel(AiModelPurpose::Assistant);

    sendAiAssistantMessageForTest('选择可用模型', Str::uuid()->toString());

    Bus::assertDispatched(RunAiChatStreamJob::class, function (RunAiChatStreamJob $job) use ($first, $second): bool {
        $candidateIds = collect($job->modelCandidates)->pluck('ai_model_id');

        return $candidateIds->count() === 2
            && $candidateIds->contains($first->id)
            && $candidateIds->contains($second->id);
    });
});

test('它拒绝过大的聊天历史来自应用路由', function () {
    Bus::fake();
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $app = createSystemSettings();
    attachMember($user);
    seedAiAssistantModel();

    $this->actingAs($user)
        ->postJson(route('app.ai-chat.messages.store', []), [
            'conversation_id' => $conversation->id,
            'prompt' => 'hello',
            'round_id' => Str::uuid()->toString(),
            'history' => collect(range(1, 21))
                ->map(fn (): array => ['role' => 'user', 'content' => 'hello'])
                ->all(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['history']);

    Bus::assertNotDispatched(RunAiChatStreamJob::class);
});

test('它拒绝空提示词在派发前', function () {
    Bus::fake();
    $app = createSystemSettings();
    seedAiAssistantModel();

    expect(fn () => sendAiAssistantMessageForTest("   \n\t", Str::uuid()->toString(), []))
        ->toThrow(ValidationException::class);

    Bus::assertNotDispatched(RunAiChatStreamJob::class);
});

test('它返回该轮对话主题并按 round_id 派发 job', function () {
    Bus::fake();
    $app = createSystemSettings();
    seedAiAssistantModel();

    $roundId = Str::uuid()->toString();

    $result = sendAiAssistantMessageForTest('hello', $roundId, []);

    expect($result['topic'])->toBe(MercureTopics::aiChat($roundId));

    Bus::assertDispatched(
        RunAiChatStreamJob::class,
        fn (RunAiChatStreamJob $job): bool => $job->roundId === $roundId,
    );
});

test('它把常规设置中的系统名称传给流式 job', function () {
    Bus::fake();
    createSystemSettings(['name' => 'HelmDesk2']);
    seedAiAssistantModel();

    sendAiAssistantMessageForTest('你是谁', Str::uuid()->toString(), []);

    Bus::assertDispatched(
        RunAiChatStreamJob::class,
        fn (RunAiChatStreamJob $job): bool => $job->systemName === 'HelmDesk2',
    );
});

test('它拒绝非法的 round_id 在派发前', function () {
    Bus::fake();
    $app = createSystemSettings();
    seedAiAssistantModel();

    expect(fn () => sendAiAssistantMessageForTest('hello', 'not-a-uuid', []))
        ->toThrow(ValidationException::class);

    Bus::assertNotDispatched(RunAiChatStreamJob::class);
});

test('它把用户提示词作为末条消息派发', function () {
    Bus::fake();
    $app = createSystemSettings();
    seedAiAssistantModel();

    $prompt = str_repeat('a', 9000);

    sendAiAssistantMessageForTest($prompt, Str::uuid()->toString(), []);

    Bus::assertDispatched(RunAiChatStreamJob::class, fn (RunAiChatStreamJob $job): bool => ($job->messages[0]['content'] ?? null) === $prompt);
});

test('它把本轮附件 ID 作为末条消息派发，且允许纯附件无文本', function () {
    Bus::fake();
    $app = createSystemSettings();
    seedAiAssistantModel();

    sendAiAssistantMessageForTest(
        '',
        Str::uuid()->toString(),
        [],
        ['att-1', 'att-2'],
    );

    Bus::assertDispatched(RunAiChatStreamJob::class, function (RunAiChatStreamJob $job): bool {
        $last = $job->messages[array_key_last($job->messages)];

        return $last['role'] === 'user'
            && $last['content'] === ''
            && $last['attachment_ids'] === ['att-1', 'att-2'];
    });
});

test('它拒绝既无文本也无附件的请求在派发前', function () {
    Bus::fake();
    $app = createSystemSettings();
    seedAiAssistantModel();

    expect(fn () => sendAiAssistantMessageForTest('', Str::uuid()->toString(), [], []))
        ->toThrow(ValidationException::class);

    Bus::assertNotDispatched(RunAiChatStreamJob::class);
});

test('应用路由是限流到合理数字的请求每分钟', function () {
    Bus::fake();
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $app = createSystemSettings();
    attachMember($user);
    seedAiAssistantModel();

    // 30 是当前 throttle:30,1 的阈值；第 31 次必须被 RateLimiter 截下来。
    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($user)
            ->postJson(route('app.ai-chat.messages.store', []), [
                'conversation_id' => $conversation->id,
                'prompt' => 'hi',
                'round_id' => Str::uuid()->toString(),
            ]);
    }

    $this->actingAs($user)
        ->postJson(route('app.ai-chat.messages.store', []), [
            'conversation_id' => $conversation->id,
            'prompt' => 'hi',
            'round_id' => Str::uuid()->toString(),
        ])
        ->assertStatus(429);
});

test('它拒绝聊天请求当 assistant 用途池没有可用模型', function () {
    Bus::fake();
    $app = createSystemSettings();
    // 不 seed 任何 assistant 模型。

    expect(fn () => sendAiAssistantMessageForTest('hello', Str::uuid()->toString()))
        ->toThrow(ValidationException::class);

    Bus::assertNotDispatched(RunAiChatStreamJob::class);
});

test('它拒绝聊天请求当唯一 assistant 模型被停用', function () {
    Bus::fake();
    $app = createSystemSettings();
    makeAiModel(AiModelPurpose::Assistant, isActive: false);

    expect(fn () => sendAiAssistantMessageForTest('hello', Str::uuid()->toString(), []))
        ->toThrow(ValidationException::class);

    Bus::assertNotDispatched(RunAiChatStreamJob::class);
});

test('它拒绝无效历史角色在派发前', function () {
    Bus::fake();
    $app = createSystemSettings();
    seedAiAssistantModel();

    expect(fn () => sendAiAssistantMessageForTest(
        'hello',
        Str::uuid()->toString(),
        [['role' => 'bot', 'content' => 'unsupported role']],
    ))->toThrow(ValidationException::class);

    Bus::assertNotDispatched(RunAiChatStreamJob::class);
});

test('它把已启用的 MCP 服务和工具白名单随 job 下发', function () {
    Bus::fake();
    $app = createSystemSettings();
    seedAiAssistantModel();

    // 期望被推送的集成：上次同步未失败且至少有 1 个 is_enabled 工具。
    $activeServer = Integration::factory()
        ->withBearerToken('mcp-token')
        ->create([
            'endpoint_url' => 'https://mcp.example.com/active',
            'headers' => ['X-System' => 'helmdesk'],
            'timeout_seconds' => 45,
            'sort_order' => 1,
        ]);
    IntegrationTool::factory()->for($activeServer, 'server')->create(['name' => 'search_orders', 'is_enabled' => true]);
    IntegrationTool::factory()->for($activeServer, 'server')->create(['name' => 'cancel_order', 'is_enabled' => true]);
    // 已下线工具不应进入白名单。
    IntegrationTool::factory()->removed()->for($activeServer, 'server')->create(['name' => 'removed_op']);
    // is_enabled = false 工具不应进入白名单。
    IntegrationTool::factory()->for($activeServer, 'server')->create(['name' => 'paused_op', 'is_enabled' => false]);

    // 期望被跳过的集成：上次同步失败，不挂给 AI Agent。
    $failedServer = Integration::factory()->syncFailed()->create([
        'endpoint_url' => 'https://mcp.example.com/failed',
    ]);
    IntegrationTool::factory()->for($failedServer, 'server')->create(['name' => 'noop', 'is_enabled' => true]);

    // 期望被跳过的集成：没有可用工具。
    $emptyServer = Integration::factory()->create([
        'endpoint_url' => 'https://mcp.example.com/empty',
        'sort_order' => 0,
    ]);
    IntegrationTool::factory()->removed()->for($emptyServer, 'server')->create(['name' => 'gone']);

    sendAiAssistantMessageForTest('hello', Str::uuid()->toString(), []);

    Bus::assertDispatched(RunAiChatStreamJob::class, function (RunAiChatStreamJob $job) use ($activeServer): bool {
        $integrationToolSources = $job->integrationToolSources;
        if (count($integrationToolSources) !== 1) {
            return false;
        }

        $server = $integrationToolSources[0];

        return $server->id === $activeServer->id
            && $server->slug === $activeServer->slug
            && $server->endpoint_url === 'https://mcp.example.com/active'
            && $server->transport === IntegrationTransport::StreamableHttp
            && $server->timeout_seconds === 45
            && ($server->credentials['auth_header_value'] ?? null) === 'Bearer mcp-token'
            && ($server->headers['X-System'] ?? null) === 'helmdesk'
            && count($server->tool_names) === 2
            && in_array('search_orders', $server->tool_names, true)
            && in_array('cancel_order', $server->tool_names, true);
    });
});

test('它在应用没有可用 MCP 工具时随 job 下发空数组', function () {
    Bus::fake();
    $app = createSystemSettings();
    seedAiAssistantModel();

    sendAiAssistantMessageForTest('hello', Str::uuid()->toString(), []);

    Bus::assertDispatched(RunAiChatStreamJob::class, fn (RunAiChatStreamJob $job): bool => $job->integrationToolSources === []);
});

test('它把当前系统的知识库列表随 job 下发', function () {
    Bus::fake();
    $app = createSystemSettings();
    seedAiAssistantModel();

    $kb = KnowledgeBase::factory()->create([
        'name' => '客服 FAQ',
        'description' => '常见问题与回复模板',
    ]);

    sendAiAssistantMessageForTest('hello', Str::uuid()->toString(), []);

    Bus::assertDispatched(RunAiChatStreamJob::class, function (RunAiChatStreamJob $job) use ($kb): bool {
        $bases = $job->knowledgeBases;
        if (count($bases) !== 1) {
            return false;
        }

        return ($bases[0]['id'] ?? null) === $kb->id
            && ($bases[0]['name'] ?? null) === '客服 FAQ'
            && ($bases[0]['description'] ?? null) === '常见问题与回复模板';
    });
});

test('它把 MCP 空凭据和空请求头归一化为空 map', function () {
    Bus::fake();
    $app = createSystemSettings();
    seedAiAssistantModel();

    $server = Integration::factory()->create([
        'endpoint_url' => 'https://mcp.example.com/no-auth',
        'credentials' => null,
        'headers' => null,
    ]);
    IntegrationTool::factory()->for($server, 'server')->create(['name' => 'lookup', 'is_enabled' => true]);

    sendAiAssistantMessageForTest('hello', Str::uuid()->toString(), []);

    Bus::assertDispatched(RunAiChatStreamJob::class, function (RunAiChatStreamJob $job): bool {
        $server = $job->integrationToolSources[0] ?? null;

        return $server !== null
            && $server->credentials === []
            && $server->headers === [];
    });
});
