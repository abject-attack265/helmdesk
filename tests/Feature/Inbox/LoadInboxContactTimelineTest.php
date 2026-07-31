<?php

use App\Actions\Contact\ShowContactConversationTimelineAction;
use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\ConversationTimelineEntryType;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

test('联系人时间线拒绝非法游标和冲突定位参数', function () {
    $contact = Contact::factory()->create();
    $url = '/app/inbox/contacts/'.$contact->id.'/timeline';

    $this->actingAs($this->user)
        ->getJson($url.'?before=invalid-cursor')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('before');

    $this->getJson($url.'?before=invalid-cursor&after=invalid-cursor')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['before', 'after']);
});

test('联系人时间线索引缺少源记录时记录上下文并显性失败', function () {
    $contact = Contact::factory()->create();
    $conversation = Conversation::factory()
        ->forContact($contact)
        ->create();
    $missingMessageId = (string) Str::ulid();

    DB::table('conversation_timeline_entries')->insert([
        'id' => (string) Str::ulid(),
        'contact_id' => $contact->id,
        'conversation_id' => $conversation->id,
        'entry_type' => ConversationTimelineEntryType::Message,
        'entry_id' => $missingMessageId,
        'occurred_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Log::spy();

    expect(fn () => ShowContactConversationTimelineAction::run($contact))
        ->toThrow(LogicException::class);
    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $_message, array $context): bool => $context['contact_id'] === (string) $contact->id
            && $context['entries'] === ['message:'.$missingMessageId]);
});

test('联系人时间线默认返回最新窗口并可以继续加载更早消息', function () {
    $contact = Contact::factory()->create([
    ]);

    $conversation = Conversation::factory()
        ->forContact($contact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $baseTime = now()->subHour()->startOfSecond();
    $messages = collect(range(1, 5))->map(fn (int $index) => ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '第 '.$index.' 条消息',
        'sender_name' => '访客',
        'created_at' => $baseTime->copy()->addMinutes($index),
        'updated_at' => $baseTime->copy()->addMinutes($index),
    ]));

    expect(DB::table('conversation_timeline_entries')->where('entry_id', $messages[0]->id)->exists())->toBeTrue();

    $latest = $this->actingAs($this->user)
        ->getJson('/app'.'/inbox/contacts/'.$contact->id.'/timeline?per_page=3')
        ->assertOk()
        ->assertJsonPath('timeline.next_cursor', null)
        ->json('timeline');

    expect(collect($latest['entries'])->pluck('id')->all())->toBe([
        $messages[2]->id,
        $messages[3]->id,
        $messages[4]->id,
    ]);
    expect($latest['previous_cursor'])->not->toBeNull();

    $older = $this->actingAs($this->user)
        ->getJson('/app'.'/inbox/contacts/'.$contact->id.'/timeline?per_page=3&before='.urlencode($latest['previous_cursor']))
        ->assertOk()
        ->json('timeline');

    expect(collect($older['entries'])->pluck('id')->all())->toBe([
        $messages[0]->id,
        $messages[1]->id,
    ]);
    expect($older['previous_cursor'])->toBeNull();
    expect($older['next_cursor'])->not->toBeNull();
});

test('联系人时间线只返回当前窗口涉及的会话摘要并保留联系人内会话序号', function () {
    $contact = Contact::factory()->create();
    $baseTime = now()->subDays(3)->startOfSecond();
    $conversations = collect(range(1, 3))->map(function (int $index) use ($contact, $baseTime): Conversation {
        $createdAt = $baseTime->copy()->addDay($index);
        $conversation = Conversation::factory()
            ->forContact($contact)
            ->create([
                'status' => ConversationStatus::Open,
                'inbox_status' => ConversationInboxStatus::TeammateHandling,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'last_message_at' => $createdAt,
            ]);

        ConversationMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Visitor,
            'kind' => MessageKind::Text,
            'content' => '第 '.$index.' 次会话消息',
            'sender_name' => '访客',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $conversation;
    });

    $latest = $this->actingAs($this->user)
        ->getJson('/app/inbox/contacts/'.$contact->id.'/timeline?per_page=1')
        ->assertOk()
        ->json('timeline');

    expect(collect($latest['conversations'])->pluck('id')->all())
        ->toBe([(string) $conversations[2]->id])
        ->and($latest['conversation_sequence_by_id'])
        ->toBe([(string) $conversations[2]->id => 3]);

    $older = $this->actingAs($this->user)
        ->getJson('/app/inbox/contacts/'.$contact->id.'/timeline?per_page=1&before='.urlencode($latest['previous_cursor']))
        ->assertOk()
        ->json('timeline');

    expect(collect($older['conversations'])->pluck('id')->all())
        ->toBe([(string) $conversations[1]->id])
        ->and($older['conversation_sequence_by_id'])
        ->toBe([(string) $conversations[1]->id => 2]);
});

test('联系人时间线在相同发生时间下按条目 ID 稳定分页和定位锚点', function () {
    $contact = Contact::factory()->create();
    $conversation = Conversation::factory()
        ->forContact($contact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);
    $occurredAt = now()->subHour()->startOfSecond();
    $messages = collect(range(1, 5))->map(fn (int $index) => ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '同时消息 '.$index,
        'sender_name' => '访客',
        'created_at' => $occurredAt,
        'updated_at' => $occurredAt,
    ]));
    $orderedMessageIds = DB::table('conversation_timeline_entries')
        ->whereIn('entry_id', $messages->pluck('id')->all())
        ->orderBy('occurred_at')
        ->orderBy('id')
        ->pluck('entry_id')
        ->all();

    $latest = $this->actingAs($this->user)
        ->getJson('/app/inbox/contacts/'.$contact->id.'/timeline?per_page=2')
        ->assertOk()
        ->json('timeline');

    expect(collect($latest['entries'])->pluck('id')->all())->toBe(array_slice($orderedMessageIds, 3, 2));

    $older = $this->actingAs($this->user)
        ->getJson('/app/inbox/contacts/'.$contact->id.'/timeline?per_page=2&before='.urlencode($latest['previous_cursor']))
        ->assertOk()
        ->json('timeline');

    expect(collect($older['entries'])->pluck('id')->all())->toBe(array_slice($orderedMessageIds, 1, 2));

    $newer = $this->actingAs($this->user)
        ->getJson('/app/inbox/contacts/'.$contact->id.'/timeline?per_page=2&after='.urlencode($older['next_cursor']))
        ->assertOk()
        ->json('timeline');

    expect(collect($newer['entries'])->pluck('id')->all())->toBe(array_slice($orderedMessageIds, 3, 2));

    $anchor = $this->actingAs($this->user)
        ->getJson('/app/inbox/contacts/'.$contact->id.'/timeline?per_page=3&anchor_type=message&anchor_id='.$orderedMessageIds[2])
        ->assertOk()
        ->assertJsonPath('timeline.anchor_entry_id', $orderedMessageIds[2])
        ->json('timeline');

    expect(collect($anchor['entries'])->pluck('id')->all())->toBe(array_slice($orderedMessageIds, 1, 3));
});

test('联系人时间线可以加载搜索消息所在的锚点窗口', function () {
    $contact = Contact::factory()->create([
    ]);

    $conversation = Conversation::factory()
        ->forContact($contact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $baseTime = now()->subHour()->startOfSecond();
    $messages = collect(range(1, 6))->map(fn (int $index) => ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '锚点测试 '.$index,
        'sender_name' => '访客',
        'created_at' => $baseTime->copy()->addMinutes($index),
        'updated_at' => $baseTime->copy()->addMinutes($index),
    ]));

    $target = $messages[1];

    $timeline = $this->actingAs($this->user)
        ->getJson('/app'.'/inbox/contacts/'.$contact->id.'/timeline?per_page=3&anchor_type=message&anchor_id='.$target->id)
        ->assertOk()
        ->assertJsonPath('timeline.anchor_entry_id', $target->id)
        ->json('timeline');

    expect(collect($timeline['entries'])->pluck('id')->all())->toBe([
        $messages[0]->id,
        $messages[1]->id,
        $messages[2]->id,
    ]);
    expect($timeline['previous_cursor'])->toBeNull();
    expect($timeline['next_cursor'])->not->toBeNull();
});

test('联系人时间线按同一索引混合排序消息和事件', function () {
    $contact = Contact::factory()->create([
    ]);

    $conversation = Conversation::factory()
        ->forContact($contact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $baseTime = now()->subHour()->startOfSecond();

    $firstMessage = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '事件前消息',
        'sender_name' => '访客',
        'created_at' => $baseTime->copy()->addMinute(),
        'updated_at' => $baseTime->copy()->addMinute(),
    ]);

    $event = ConversationEvent::factory()->forConversation($conversation)->create([
        'type' => ConversationEventType::HandoffRequested,
        'payload' => ['reason' => 'user_requested'],
        'created_at' => $baseTime->copy()->addMinutes(2),
    ]);

    $secondMessage = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => '事件后消息',
        'sender_name' => '客服',
        'created_at' => $baseTime->copy()->addMinutes(3),
        'updated_at' => $baseTime->copy()->addMinutes(3),
    ]);

    $timeline = $this->actingAs($this->user)
        ->getJson('/app'.'/inbox/contacts/'.$contact->id.'/timeline?per_page=10')
        ->assertOk()
        ->json('timeline');

    expect(collect($timeline['entries'])->map(fn (array $entry) => [$entry['type'], $entry['id']])->all())->toBe([
        ['message', $firstMessage->id],
        ['event', $event->id],
        ['message', $secondMessage->id],
    ]);
});

test('联系人时间线不展示接待轮次边界记录', function () {
    $contact = Contact::factory()->create([
    ]);
    $conversation = Conversation::factory()
        ->forContact($contact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);
    $baseTime = now()->subHour()->startOfSecond();
    $firstMessage = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '第一条消息',
        'sender_name' => '访客',
        'created_at' => $baseTime->copy()->addMinute(),
        'updated_at' => $baseTime->copy()->addMinute(),
    ]);
    ConversationEvent::factory()->forConversation($conversation)->create([
        'type' => ConversationEventType::ReceptionTurnStarted,
        'payload' => ['turn_id' => 'turn-1'],
        'created_at' => $baseTime->copy()->addMinutes(2),
    ]);
    ConversationEvent::factory()->forConversation($conversation)->create([
        'type' => ConversationEventType::ReceptionTurnEnded,
        'payload' => ['turn_id' => 'turn-1'],
        'created_at' => $baseTime->copy()->addMinutes(3),
    ]);
    $secondMessage = ConversationMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => '第二条消息',
        'sender_name' => '客服',
        'created_at' => $baseTime->copy()->addMinutes(5),
        'updated_at' => $baseTime->copy()->addMinutes(5),
    ]);

    $timeline = $this->actingAs($this->user)
        ->getJson('/app'.'/inbox/contacts/'.$contact->id.'/timeline?per_page=2')
        ->assertOk()
        ->json('timeline');

    expect(collect($timeline['entries'])->pluck('id')->all())->toBe([
        $firstMessage->id,
        $secondMessage->id,
    ]);
});
