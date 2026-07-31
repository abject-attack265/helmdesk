<?php

use App\Data\Reception\Runtime\ReceptionToolEventContextData;
use App\Data\Reception\Runtime\ReceptionToolEventDefinitionData;
use App\Enums\ConversationEventType;
use App\Events\Reception\InstanceReceptionUpdated;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Services\Reception\ReceptionToolErrorHandler;
use App\Services\Reception\ReceptionToolEventObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use NeuronAI\Observability\Events\ToolCalled;
use NeuronAI\Tools\Tool;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    DB::rollBack();
});

afterEach(function (): void {
    DB::table('conversation_timeline_entries')->delete();
    DB::table('conversation_events')->delete();
    DB::table('conversations')->delete();
    DB::beginTransaction();
});

test('接待集成工具执行后记录可展示事件并通知收件箱', function () {
    Event::fake([InstanceReceptionUpdated::class]);

    $conversation = Conversation::factory()->create();
    $context = new ReceptionToolEventContextData(
        conversation_id: (string) $conversation->id,
        turn_id: 'turn-1',
        definitions: [
            new ReceptionToolEventDefinitionData(
                tool_name: 'search_orders',
                source_type: 'integration',
                source_names: ['JCTRADE'],
                description: null,
            ),
        ],
    );
    $tool = Tool::make('search_orders')
        ->setInputs(['order_no' => 'A001'])
        ->setCallId('call-1')
        ->setResult(['order_no' => 'A001', 'status' => 'shipped']);

    (new ReceptionToolEventObserver($context))->onEvent(
        'tool-called',
        new stdClass,
        new ToolCalled($tool),
    );

    $event = ConversationEvent::query()->sole();

    expect($event->type)->toBe(ConversationEventType::ReceptionToolCalled)
        ->and($event->payload['tool'])->toBe('search_orders')
        ->and($event->payload['display_name'])->toBe('Search Orders')
        ->and($event->payload['status'])->toBe('success')
        ->and(DB::table('conversation_timeline_entries')->where('entry_id', $event->id)->exists())->toBeTrue();

    Event::assertDispatched(
        InstanceReceptionUpdated::class,
        fn (InstanceReceptionUpdated $notification): bool => $notification->payload['event_id'] === (string) $event->id,
    );
});

test('未登记的出口工具不生成重复事件', function (string $toolName) {
    $conversation = Conversation::factory()->create();
    $context = new ReceptionToolEventContextData(
        conversation_id: (string) $conversation->id,
        turn_id: 'turn-1',
        definitions: [],
    );
    $tool = Tool::make($toolName)
        ->setInputs(['message' => '请稍候'])
        ->setResult(['ok' => true]);

    (new ReceptionToolEventObserver($context))->onEvent(
        'tool-called',
        new stdClass,
        new ToolCalled($tool),
    );

    expect(ConversationEvent::query()->count())->toBe(0);
})->with([
    '过程回复' => ['respond'],
    '转接人工' => ['handoff_to_human'],
]);

test('工具返回标准错误结果时记录失败状态', function () {
    $conversation = Conversation::factory()->create();
    $context = new ReceptionToolEventContextData(
        conversation_id: (string) $conversation->id,
        turn_id: 'turn-1',
        definitions: [
            new ReceptionToolEventDefinitionData(
                tool_name: 'knowledge_search',
                source_type: 'knowledge_base',
                source_names: ['售后知识库'],
                description: '检索知识库。',
            ),
        ],
    );
    $tool = Tool::make('knowledge_search', '检索知识库。')
        ->setInputs(['mode' => 'hybrid', 'query' => ['退款']])
        ->setResult(['error' => 'knowledge_base_inaccessible']);

    (new ReceptionToolEventObserver($context))->onEvent(
        'tool-called',
        new stdClass,
        new ToolCalled($tool),
    );

    $payload = ConversationEvent::query()->sole()->payload;

    expect($payload['status'])->toBe('failed')
        ->and($payload['display_name'])->toBe('检索知识库');
});

test('工具返回空错误值时记录成功状态', function (mixed $error) {
    $conversation = Conversation::factory()->create();
    $context = new ReceptionToolEventContextData(
        conversation_id: (string) $conversation->id,
        turn_id: 'turn-1',
        definitions: [
            new ReceptionToolEventDefinitionData(
                tool_name: 'search_orders',
                source_type: 'integration',
                source_names: ['JCTRADE'],
                description: null,
            ),
        ],
    );
    $tool = Tool::make('search_orders')
        ->setResult([
            'data' => ['order_no' => 'A001'],
            'error' => $error,
        ]);

    (new ReceptionToolEventObserver($context))->onEvent(
        'tool-called',
        new stdClass,
        new ToolCalled($tool),
    );

    expect(ConversationEvent::query()->sole()->payload['status'])->toBe('success');
})->with([
    'null' => [null],
    'false' => [false],
]);

test('可展示工具抛出异常时转换为失败事件', function () {
    $conversation = Conversation::factory()->create();
    $context = new ReceptionToolEventContextData(
        conversation_id: (string) $conversation->id,
        turn_id: 'turn-1',
        definitions: [
            new ReceptionToolEventDefinitionData(
                tool_name: 'remote_search',
                source_type: 'integration',
                source_names: ['远程系统'],
                description: null,
            ),
        ],
    );
    $tool = Tool::make('remote_search')
        ->setInputs(['keyword' => 'A001'])
        ->setCallId('call-1');

    $result = (new ReceptionToolErrorHandler($context))(
        new RuntimeException('connection failed'),
        $tool,
    );
    $tool->setResult($result);
    (new ReceptionToolEventObserver($context))->onEvent(
        'tool-called',
        new stdClass,
        new ToolCalled($tool),
    );

    $event = ConversationEvent::query()->sole();

    expect(json_decode($result, true))->toBe(['error' => 'tool_execution_failed'])
        ->and($event->payload['status'])->toBe('failed');
});

test('出口工具抛出异常时保留原始异常并设置可读取结果', function () {
    $context = new ReceptionToolEventContextData(
        conversation_id: 'conversation-1',
        turn_id: 'turn-1',
        definitions: [],
    );
    $tool = Tool::make('respond');
    $exception = new RuntimeException('message persistence failed');

    expect(fn () => (new ReceptionToolErrorHandler($context))($exception, $tool))
        ->toThrow($exception)
        ->and(json_decode($tool->getResult(), true))->toBe(['error' => 'tool_execution_failed']);
});
