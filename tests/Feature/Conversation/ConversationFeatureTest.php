<?php

use App\Actions\Conversation\ShowConversationDetailAction;
use App\Data\Conversation\ListConversationItemData;
use App\Enums\ChannelType;
use App\Enums\ConversationEventTone;
use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationMessage;
use App\Models\ConversationThread;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

test('会话列表项展示访客消息摘要', function () {
    $channel = Channel::factory()->create();
    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->forContact($contact)
        ->for($channel)
        ->assignedTo($this->user)
        ->create([
            'last_message_preview' => 'Hello, I need help',
            'last_message_at' => now(),
        ]);
    ConversationMessage::factory()->forConversation($conversation)->visitorText()->create([
        'content' => 'Hello, I need help',
    ]);

    $thread = ConversationThread::requireForConversation($conversation)->load([
        'currentConversation.contact',
        'currentConversation.channel',
        'currentConversation.assignedUser',
        'currentConversation.receptionPlanVersion.plan',
        'currentConversation.latestMessage',
    ]);
    $item = ListConversationItemData::fromModel(
        $thread,
        0,
    );

    expect($item->last_message_preview)->toBe('Hello, I need help')
        ->and($item->last_message_translation_previews)->toBe([])
        ->and($item->last_message_can_translate)->toBeTrue();
});

test('会话列表项下发线程当前会话的渠道类型与渠道名', function () {
    $channel = Channel::factory()->create([
        'type' => ChannelType::Telegram,
        'name' => '官网客服机器人',
    ]);
    $conversation = Conversation::factory()
        ->forContact(Contact::factory()->create([]))
        ->for($channel)
        ->create([]);

    $thread = ConversationThread::requireForConversation($conversation)->load([
        'currentConversation.contact',
        'currentConversation.channel',
        'currentConversation.assignedUser',
        'currentConversation.receptionPlanVersion.plan',
        'currentConversation.latestMessage',
    ]);
    $item = ListConversationItemData::fromModel($thread, 0);

    expect($item->channel_type)->toBe(ChannelType::Telegram)
        ->and($item->channel_type_label)->toBe(ChannelType::Telegram->label())
        ->and($item->channel_name)->toBe('官网客服机器人');
});

test('会话详情返回已合并时间线在升序顺序', function () {
    $this->user->forceFill([
        'avatar' => 'https://example.com/operator.png',
    ])->save();

    $contact = Contact::factory()->create([
        'name' => 'Alice Example',
    ]);
    $plan = ReceptionPlan::factory()->create([
        'name' => 'Support Plan-'.Str::lower(Str::random(6)),
    ]);
    $version = ReceptionPlanVersion::factory()->for($plan, 'plan')->create();

    $conversation = Conversation::factory()
        ->forContact($contact)
        ->assignedTo($this->user)
        ->create([
            'reception_plan_version_id' => $version->id,
            'subject' => 'Timeline test',
        ]);

    $firstAt = CarbonImmutable::parse('2026-04-18 09:00:00');
    $secondAt = $firstAt->addMinutes(1);
    $thirdAt = $secondAt->addMinutes(1);
    $fourthAt = $thirdAt->addMinutes(1);

    ConversationMessage::factory()->forConversation($conversation)->create([
        'sender_user_id' => null,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'Hello there',
        'created_at' => $firstAt,
        'updated_at' => $firstAt,
    ]);

    ConversationEvent::factory()->forConversation($conversation)->create([
        'actor_user_id' => $this->user->id,
        'type' => ConversationEventType::Created,
        'payload' => ['source' => 'manual'],
        'created_at' => $secondAt,
    ]);

    ConversationMessage::factory()->forConversation($conversation)->create([
        'sender_user_id' => null,
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => 'I can help with that.',
        'created_at' => $thirdAt,
        'updated_at' => $thirdAt,
    ]);

    ConversationMessage::factory()->forConversation($conversation)->create([
        'sender_user_id' => $this->user->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => '',
        'created_at' => $fourthAt,
        'updated_at' => $fourthAt,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('app.conversations.show', ['id' => $conversation->id]));

    $response->assertOk()
        ->assertJsonPath('conversation.id', $conversation->id)
        ->assertJsonPath('timeline.items.0.subtype', 'message:visitor:text')
        ->assertJsonPath('timeline.items.1.subtype', 'event:created')
        ->assertJsonPath('timeline.items.2.subtype', 'message:ai:text')
        ->assertJsonPath('timeline.items.3.subtype', 'message:teammate:text')
        ->assertJsonPath('timeline.items.0.type', 'message')
        ->assertJsonPath('timeline.items.0.role', MessageRole::Visitor->value)
        ->assertJsonPath('timeline.items.0.kind', MessageKind::Text->value)
        ->assertJsonPath('timeline.items.0.event_type', null)
        ->assertJsonPath('timeline.items.1.type', 'event')
        ->assertJsonPath('timeline.items.1.role', null)
        ->assertJsonPath('timeline.items.1.kind', null)
        ->assertJsonPath('timeline.items.1.event_type', ConversationEventType::Created->value)
        ->assertJsonPath('timeline.items.2.role', MessageRole::Ai->value)
        ->assertJsonPath('timeline.items.2.kind', MessageKind::Text->value)
        ->assertJsonPath('timeline.items.0.content', 'Hello there')
        ->assertJsonPath('timeline.items.1.event_display.summary', $this->user->name.'手动创建了此会话')
        ->assertJsonPath('timeline.items.1.event_display.tone', ConversationEventTone::Muted->value)
        ->assertJsonPath('timeline.items.2.content', 'I can help with that.')
        ->assertJsonPath('timeline.items.2.sender_name', 'AI')
        ->assertJsonPath('timeline.items.3.sender_name', $this->user->name)
        ->assertJsonPath('timeline.items.3.sender_avatar_url', 'https://example.com/operator.png')
        ->assertJsonPath('timeline.items.3.content', '');

    expect($response->json('timeline.items'))->toHaveCount(4)
        ->and($response->json('timeline.items.0.occurred_at'))->toBe($firstAt->toIso8601String())
        ->and($response->json('timeline.items.1.occurred_at'))->toBe($secondAt->toIso8601String())
        ->and($response->json('timeline.items.2.occurred_at'))->toBe($thirdAt->toIso8601String())
        ->and($response->json('timeline.items.3.occurred_at'))->toBe($fourthAt->toIso8601String());
});

test('会话详情游标分页时间线块正确', function () {
    $conversation = Conversation::factory()->create([
    ]);

    $firstAt = CarbonImmutable::parse('2026-04-18 10:00:00');
    $secondAt = $firstAt->addMinutes(1);
    $thirdAt = $secondAt->addMinutes(1);

    ConversationMessage::factory()->forConversation($conversation)->create([
        'sender_user_id' => null,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'Oldest',
        'created_at' => $firstAt,
        'updated_at' => $firstAt,
    ]);

    ConversationEvent::factory()->forConversation($conversation)->create([
        'actor_user_id' => $this->user->id,
        'type' => ConversationEventType::AssignmentChanged,
        'payload' => ['source' => 'claim', 'user_id' => (string) $this->user->id],
        'created_at' => $secondAt,
    ]);

    ConversationMessage::factory()->forConversation($conversation)->create([
        'sender_user_id' => $this->user->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'Newest',
        'created_at' => $thirdAt,
        'updated_at' => $thirdAt,
    ]);

    $action = app(ShowConversationDetailAction::class);

    $firstPage = $action->handle($conversation, null, 2);

    expect($firstPage->timeline->items)->toHaveCount(2)
        ->and($firstPage->timeline->items[0]->occurred_at)->toBe($secondAt->toIso8601String())
        ->and($firstPage->timeline->items[1]->occurred_at)->toBe($thirdAt->toIso8601String())
        ->and($firstPage->timeline->next_cursor)->not->toBeNull();

    $secondPage = $action->handle($conversation, $firstPage->timeline->next_cursor, 2);

    expect($secondPage->timeline->items)->toHaveCount(1)
        ->and($secondPage->timeline->items[0]->content)->toBe('Oldest')
        ->and($secondPage->timeline->items[0]->occurred_at)->toBe($firstAt->toIso8601String())
        ->and($secondPage->timeline->next_cursor)->toBeNull();
});

test('会话详情拒绝格式错误的时间线游标', function () {
    $conversation = Conversation::factory()->create([]);
    $yearZeroCursor = base64_encode(json_encode([
        'occurred_at' => '0000-01-01 00:00:00',
        'id' => (string) Str::ulid(),
    ], JSON_THROW_ON_ERROR));

    expect(fn () => ShowConversationDetailAction::run($conversation, 'invalid-cursor'))
        ->toThrow(ValidationException::class)
        ->and(fn () => ShowConversationDetailAction::run($conversation, $yearZeroCursor))
        ->toThrow(ValidationException::class);

    $this->actingAs($this->user)
        ->getJson(route('app.conversations.show', [
            'id' => $conversation->id,
            'cursor' => ['invalid'],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cursor');
});

test('会话详情展示接待 agent 的客服可读事件', function () {
    app()->setLocale('zh_CN');

    $conversation = Conversation::factory()->create([
    ]);

    $baseAt = CarbonImmutable::parse('2026-04-18 12:00:00');

    ConversationEvent::factory()->forConversation($conversation)->create([
        'type' => ConversationEventType::ReceptionToolCalled,
        'payload' => [
            'tool' => 'search_orders',
            'source_type' => 'integration',
            'display_name' => '查询订单信息',
            'status' => 'success',
        ],
        'created_at' => $baseAt,
    ]);

    ConversationEvent::factory()->forConversation($conversation)->create([
        'type' => ConversationEventType::ReceptionToolCalled,
        'payload' => [
            'tool' => 'knowledge_search',
            'source_type' => 'knowledge_base',
            'display_name' => '检索知识库',
            'status' => 'failed',
        ],
        'created_at' => $baseAt->addSecond(),
    ]);

    $detail = ShowConversationDetailAction::run($conversation);
    $knowledgeDisplay = $detail->timeline->items[1]->event_display;

    expect($detail->timeline->items)->toHaveCount(2)
        ->and($detail->timeline->items[0]->event_display->tone)->toBe(ConversationEventTone::Muted)
        ->and($detail->timeline->items[0]->event_display->facts)->toBe([])
        ->and($knowledgeDisplay->tone)->toBe(ConversationEventTone::Warning)
        ->and($knowledgeDisplay->detail)->toBeNull()
        ->and($knowledgeDisplay->facts)->toBe([]);
});

test('会话详情不展示接待轮次边界记录', function () {
    $conversation = Conversation::factory()->create([
    ]);
    $message = ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '访客消息',
    ]);
    ConversationEvent::factory()->forConversation($conversation)->create([
        'type' => ConversationEventType::ReceptionTurnStarted,
        'payload' => ['turn_id' => 'turn-1'],
    ]);
    ConversationEvent::factory()->forConversation($conversation)->create([
        'type' => ConversationEventType::ReceptionTurnEnded,
        'payload' => ['turn_id' => 'turn-1'],
    ]);
    $detail = ShowConversationDetailAction::run($conversation);

    expect($detail->timeline->items)->toHaveCount(1)
        ->and($detail->timeline->items[0]->id)->toBe((string) $message->id);
});

test('会话详情事件展示用摘要承载客服需要的信息', function () {
    app()->setLocale('zh_CN');

    $previousUser = User::factory()->create(['name' => '李四']);
    attachMember($previousUser);

    $targetUser = User::factory()->create(['name' => '王五']);
    attachMember($targetUser);

    $conversation = Conversation::factory()->create([
    ]);

    $baseAt = CarbonImmutable::parse('2026-04-18 13:00:00');
    $events = [
        [
            'type' => ConversationEventType::HandoffRequested,
            'payload' => ['reason' => 'user_requested'],
            'summary' => '访客要求转人工',
            'tone' => ConversationEventTone::Normal,
        ],
        [
            'type' => ConversationEventType::HandoffRequested,
            'payload' => ['reason' => 'ai_requested'],
            'summary' => 'AI 判断此会话需要人工处理',
            'tone' => ConversationEventTone::Important,
        ],
        [
            'type' => ConversationEventType::HandoffRequested,
            'payload' => ['reason' => 'low_confidence'],
            'summary' => 'AI 不确定如何回答，已转人工',
            'tone' => ConversationEventTone::Normal,
        ],
        [
            'type' => ConversationEventType::HandoffRequested,
            'payload' => ['reason' => 'tool_failure'],
            'summary' => 'AI 处理时遇到异常，已转人工',
            'tone' => ConversationEventTone::Warning,
        ],
        [
            'type' => ConversationEventType::HandoffRequested,
            'payload' => ['reason' => 'policy_required'],
            'summary' => '按业务规则需人工处理',
            'tone' => ConversationEventTone::Normal,
        ],
        [
            'type' => ConversationEventType::HandoffRequested,
            'payload' => ['reason' => 'user_reported_loss'],
            'summary' => 'AI 请求转人工',
            'tone' => ConversationEventTone::Normal,
        ],
        [
            'type' => ConversationEventType::AssignmentChanged,
            'actor_user_id' => $this->user->id,
            'payload' => ['source' => 'claim', 'user_id' => (string) $this->user->id],
            'summary' => $this->user->name.'接管了此会话',
            'tone' => ConversationEventTone::Normal,
        ],
        [
            'type' => ConversationEventType::AssignmentChanged,
            'actor_user_id' => $this->user->id,
            'payload' => ['source' => 'reply', 'user_id' => (string) $this->user->id],
            'summary' => $this->user->name.'回复并接管了此会话',
            'tone' => ConversationEventTone::Normal,
        ],
        [
            'type' => ConversationEventType::AssignmentChanged,
            'actor_user_id' => $this->user->id,
            'payload' => ['source' => 'transfer_to_human', 'user_id' => (string) $this->user->id],
            'summary' => $this->user->name.'接手了 AI 正在处理的会话',
            'tone' => ConversationEventTone::Normal,
        ],
        [
            'type' => ConversationEventType::AssignmentChanged,
            'actor_user_id' => $this->user->id,
            'payload' => ['source' => 'takeover', 'previous_user_id' => (string) $previousUser->id],
            'summary' => $this->user->name.'接替了李四处理此会话',
            'tone' => ConversationEventTone::Normal,
        ],
        [
            'type' => ConversationEventType::AssignmentChanged,
            'actor_user_id' => $this->user->id,
            'payload' => ['source' => 'transfer_to_teammate', 'user_id' => (string) $targetUser->id],
            'summary' => $this->user->name.'将会话转交给了王五',
            'tone' => ConversationEventTone::Normal,
        ],
        [
            'type' => ConversationEventType::AssignmentChanged,
            'actor_user_id' => $this->user->id,
            'payload' => [
                'source' => 'release_to_ai',
                'inbox_status' => ConversationInboxStatus::AiHandling->value,
            ],
            'summary' => $this->user->name.'将会话交给了 AI',
            'tone' => ConversationEventTone::Muted,
        ],
        [
            'type' => ConversationEventType::AssignmentChanged,
            'actor_user_id' => $this->user->id,
            'payload' => [
                'source' => 'release_to_ai',
                'inbox_status' => ConversationInboxStatus::TeammatePending->value,
            ],
            'summary' => $this->user->name.'将会话释放回待接待',
            'tone' => ConversationEventTone::Muted,
        ],
        [
            'type' => ConversationEventType::StatusChanged,
            'actor_user_id' => $this->user->id,
            'payload' => ['status' => ConversationStatus::Closed->value],
            'summary' => $this->user->name.'关闭了此会话',
            'tone' => ConversationEventTone::Muted,
        ],
        [
            'type' => ConversationEventType::StatusChanged,
            'actor_user_id' => $this->user->id,
            'payload' => ['status' => ConversationStatus::Open->value],
            'summary' => $this->user->name.'重新打开了此会话',
            'tone' => ConversationEventTone::Normal,
        ],
    ];

    foreach ($events as $index => $event) {
        ConversationEvent::factory()->forConversation($conversation)->create([
            'actor_user_id' => $event['actor_user_id'] ?? null,
            'type' => $event['type'],
            'payload' => $event['payload'],
            'created_at' => $baseAt->addSeconds($index),
        ]);
    }

    $detail = ShowConversationDetailAction::run($conversation);

    expect($detail->timeline->items)->toHaveCount(count($events));

    foreach ($events as $index => $event) {
        expect($detail->timeline->items[$index]->event_display->tone)->toBe($event['tone']);
    }
});

test('会话详情遇到未知事件来源时显性失败', function () {
    $conversation = Conversation::factory()->create([
    ]);

    ConversationEvent::factory()->forConversation($conversation)->create([
        'actor_user_id' => $this->user->id,
        'type' => ConversationEventType::AssignmentChanged,
        'payload' => ['source' => 'unexpected_source'],
    ]);

    expect(fn () => ShowConversationDetailAction::run($conversation))
        ->toThrow(RuntimeException::class, 'Unknown assignment_changed source');
});

test('会话消息拒绝无效角色类型组合', function () {
    $conversation = Conversation::factory()->create([
    ]);

    expect(function () use ($conversation): void {
        ConversationMessage::factory()->forConversation($conversation)->create([
            'sender_user_id' => null,
            'role' => MessageRole::Visitor,
            'kind' => MessageKind::ToolCall,
            'content' => null,
            'payload' => ['tool' => 'search_docs'],
        ]);
    })->toThrow(ValidationException::class);
});
