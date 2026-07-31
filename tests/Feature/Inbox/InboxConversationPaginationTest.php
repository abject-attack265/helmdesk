<?php

use App\Actions\Contact\UpdateContactImportanceAction;
use App\Actions\Inbox\ShowInboxAction;
use App\Data\Contact\FormUpdateContactImportanceData;
use App\Data\Inbox\InboxFiltersData;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\InboxPane;
use App\Enums\InboxView;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
    $this->channel = Channel::factory()->create();
});

/**
 * 解析收件箱 Action 返回的延迟 prop。
 *
 * @param  array<string, mixed>  $props
 */
function resolveInboxProp(array $props, string $key): mixed
{
    $value = $props[$key];

    return is_callable($value) ? $value() : $value;
}

/**
 * 逐页收集指定收件箱视图的当前会话 ID。
 *
 * @return list<string>
 */
function paginateAllInboxConversations(object $test, InboxView $view): array
{
    $action = app(ShowInboxAction::class);
    $filters = new InboxFiltersData(
        view: $view,
        channel_id: null,
        assignee: null,
        search: null,
        important_only: false,
    );

    $ids = [];
    $cursor = null;
    $seenCursors = [];

    do {
        $props = $action->handle($test->user, $filters, cursor: $cursor);
        foreach (resolveInboxProp($props, 'conversation_list') as $item) {
            $ids[] = $item->id;
        }
        $cursor = resolveInboxProp($props, 'conversation_list_next_cursor');
        if ($cursor !== null) {
            expect($seenCursors)->not->toContain($cursor);
            $seenCursors[] = $cursor;
        }
    } while ($cursor !== null);

    return $ids;
}

/**
 * 创建一个独立联系人渠道线程及其当前会话。
 *
 * @param  array<string, mixed>  $conversationAttributes
 * @param  array<string, mixed>  $contactAttributes
 */
function createPaginationThreadConversation(
    object $test,
    array $conversationAttributes = [],
    array $contactAttributes = [],
): Conversation {
    if (isset($conversationAttributes['last_message_at']) && ! isset($conversationAttributes['created_at'])) {
        $conversationAttributes['created_at'] = $conversationAttributes['last_message_at'];
        $conversationAttributes['updated_at'] = $conversationAttributes['last_message_at'];
    }

    $contact = Contact::factory()->create($contactAttributes);

    return Conversation::factory()
        ->forContactChannel($contact, $test->channel)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'assigned_user_id' => null,
            ...$conversationAttributes,
        ]);
}

test('待处理线程按游标分页且顺序稳定', function () {
    $expected = [];
    for ($i = 0; $i < 120; $i++) {
        $conversation = createPaginationThreadConversation($this, [
            'last_message_at' => Carbon::parse('2026-06-17 12:00:00')->subMinutes($i),
        ]);
        $expected[] = (string) $conversation->id;
    }

    $ids = paginateAllInboxConversations($this, InboxView::Pending);

    expect($ids)->toHaveCount(120)
        ->and(array_unique($ids))->toHaveCount(120)
        ->and($ids)->toBe($expected);
});

test('相同活动时间的线程跨页时不重复也不遗漏', function () {
    foreach (range(1, 120) as $_) {
        createPaginationThreadConversation($this, [
            'last_message_at' => Carbon::parse('2026-06-17 12:00:00'),
            'created_at' => Carbon::parse('2026-06-17 12:00:00'),
            'updated_at' => Carbon::parse('2026-06-17 12:00:00'),
        ]);
    }

    $expected = ConversationThread::query()
        ->orderByDesc('last_activity_at')
        ->orderByDesc('id')
        ->pluck('current_conversation_id')
        ->all();
    $ids = paginateAllInboxConversations($this, InboxView::Pending);

    expect($ids)->toBe($expected)
        ->and(array_unique($ids))->toHaveCount(120);
});

test('单页内的线程没有下一页游标', function () {
    foreach (range(1, 3) as $_) {
        createPaginationThreadConversation($this);
    }

    $props = app(ShowInboxAction::class)->handle(
        $this->user,
        new InboxFiltersData(view: InboxView::Pending, channel_id: null, assignee: null, search: null, important_only: false),
    );

    expect(resolveInboxProp($props, 'conversation_list'))->toHaveCount(3)
        ->and(resolveInboxProp($props, 'conversation_list_next_cursor'))->toBeNull();
});

test('收件箱页面下发下一页游标并拒绝非法游标', function () {
    foreach (range(1, 60) as $_) {
        createPaginationThreadConversation($this);
    }

    $this->actingAs($this->user)
        ->get('/app/inbox?view=pending')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('conversation_list', 50)
            ->whereNot('conversation_list_next_cursor', null)
        );

    $props = app(ShowInboxAction::class)->handle(
        $this->user,
        new InboxFiltersData(view: InboxView::Pending, channel_id: null, assignee: null, search: null, important_only: false),
        cursor: 'not-a-valid-cursor',
    );

    expect(fn () => resolveInboxProp($props, 'conversation_list'))
        ->toThrow(ValidationException::class);
});

test('游标校验时间格式并拒绝跨视图复用', function () {
    for ($i = 0; $i < 60; $i++) {
        createPaginationThreadConversation($this, [
            'last_message_at' => Carbon::parse('2026-06-17 12:00:00')->subMinutes($i),
        ]);
    }

    $action = app(ShowInboxAction::class);
    $pendingFilters = new InboxFiltersData(
        view: InboxView::Pending,
        channel_id: null,
        assignee: null,
        search: null,
        important_only: false,
    );
    $first = $action->handle($this->user, $pendingFilters);
    $cursor = resolveInboxProp($first, 'conversation_list_next_cursor');
    $cursorPayload = json_decode(
        base64_decode(strtr((string) $cursor, '-_', '+/'), true),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $cursorPayload['a'] = 'not-a-timestamp';
    $invalidTimestampCursor = rtrim(strtr(
        base64_encode(json_encode($cursorPayload, JSON_THROW_ON_ERROR)),
        '+/',
        '-_',
    ), '=');

    $invalidTimestampProps = $action->handle(
        $this->user,
        $pendingFilters,
        cursor: $invalidTimestampCursor,
    );
    createPaginationThreadConversation($this, [
        'status' => ConversationStatus::Closed,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
        'closed_at' => Carbon::parse('2026-06-18 12:00:00'),
        'last_message_at' => Carbon::parse('2026-06-18 12:00:00'),
    ]);
    $closedProps = $action->handle(
        $this->user,
        new InboxFiltersData(view: InboxView::Closed, channel_id: null, assignee: null, search: null, important_only: false),
        cursor: $cursor,
    );

    expect(fn () => resolveInboxProp($invalidTimestampProps, 'conversation_list'))
        ->toThrow(ValidationException::class);
    expect(fn () => resolveInboxProp($closedProps, 'conversation_list'))
        ->toThrow(ValidationException::class);
});

test('带游标翻页固定返回五十条会话', function () {
    for ($i = 0; $i < 120; $i++) {
        createPaginationThreadConversation($this, [
            'last_message_at' => Carbon::parse('2026-06-17 12:00:00')->subMinutes($i),
        ]);
    }

    $action = app(ShowInboxAction::class);
    $filters = new InboxFiltersData(view: InboxView::Pending, channel_id: null, assignee: null, search: null, important_only: false);
    $first = $action->handle($this->user, $filters);
    $next = $action->handle(
        $this->user,
        $filters,
        cursor: resolveInboxProp($first, 'conversation_list_next_cursor'),
    );

    expect(resolveInboxProp($next, 'conversation_list'))->toHaveCount(50);
});

test('显式线程选择不受列表首项变化影响', function () {
    $viewedContact = Contact::factory()->create();
    $viewed = Conversation::factory()->forContactChannel($viewedContact, $this->channel)->create([
        'status' => ConversationStatus::Open,
        'inbox_status' => ConversationInboxStatus::TeammatePending,
        'assigned_user_id' => null,
        'last_message_at' => Carbon::parse('2026-06-17 12:00:00'),
        'created_at' => Carbon::parse('2026-06-17 12:00:00'),
    ]);
    $bumpedContact = Contact::factory()->create();
    $bumped = Conversation::factory()->forContactChannel($bumpedContact, $this->channel)->create([
        'status' => ConversationStatus::Open,
        'inbox_status' => ConversationInboxStatus::TeammatePending,
        'assigned_user_id' => null,
        'last_message_at' => Carbon::parse('2026-06-17 13:00:00'),
        'created_at' => Carbon::parse('2026-06-17 13:00:00'),
    ]);

    $action = app(ShowInboxAction::class);
    $filters = new InboxFiltersData(view: InboxView::Pending, channel_id: null, assignee: null, search: null, important_only: false);
    $viewedThread = ConversationThread::requireForConversation($viewed->refresh());
    $props = $action->handle(
        $this->user,
        $filters,
        threadId: (string) $viewedThread->id,
    );
    $selection = resolveInboxProp($props, 'selection');

    expect(resolveInboxProp($props, 'conversation_list')[0]->id)->toBe((string) $bumped->id)
        ->and(resolveInboxProp($props, 'current_thread_id'))->toBe((string) $viewedThread->id)
        ->and($selection?->conversation->id)->toBe((string) $viewed->id)
        ->and($selection?->contact->id)->toBe((string) $viewedContact->id);

    $defaultSelection = $action->handle($this->user, $filters);
    $deselected = $action->handle($this->user, $filters, pane: InboxPane::List);

    expect(resolveInboxProp($defaultSelection, 'selection')?->conversation->id)->toBe((string) $bumped->id)
        ->and(resolveInboxProp($deselected, 'current_thread_id'))->toBeNull()
        ->and(resolveInboxProp($deselected, 'selection'))->toBeNull();
});

test('重点联系人线程跨页时保持排序优先级', function () {
    $importantIds = [];
    for ($i = 0; $i < 20; $i++) {
        $conversation = createPaginationThreadConversation($this, [
            'last_message_at' => Carbon::parse('2026-06-17 12:00:00')->subMinutes($i),
        ], ['is_important' => true]);
        $importantIds[] = (string) $conversation->id;
    }
    for ($i = 0; $i < 50; $i++) {
        createPaginationThreadConversation($this, [
            'last_message_at' => Carbon::parse('2026-06-17 13:00:00')->subMinutes($i),
        ]);
    }

    $ids = paginateAllInboxConversations($this, InboxView::Pending);

    expect($ids)->toHaveCount(70)
        ->and(array_unique($ids))->toHaveCount(70)
        ->and(array_slice($ids, 0, 20))->toBe($importantIds);
});

test('联系人重点标记变化会同步线程排序投影', function () {
    $contact = Contact::factory()->create();
    $older = Conversation::factory()
        ->forContactChannel($contact, $this->channel)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'last_message_at' => Carbon::parse('2026-06-17 12:00:00'),
            'created_at' => Carbon::parse('2026-06-17 12:00:00'),
        ])
        ->refresh();
    $newer = createPaginationThreadConversation($this, [
        'last_message_at' => Carbon::parse('2026-06-17 13:00:00'),
    ]);

    UpdateContactImportanceAction::run(
        (string) $contact->id,
        new FormUpdateContactImportanceData(is_important: true),
        $this->user,
    );

    $props = app(ShowInboxAction::class)->handle(
        $this->user,
        new InboxFiltersData(view: InboxView::Pending, channel_id: null, assignee: null, search: null, important_only: false),
    );

    expect(ConversationThread::requireForConversation($older)->is_important)->toBeTrue()
        ->and(resolveInboxProp($props, 'conversation_list')[0]->id)->toBe($older->id)
        ->and(resolveInboxProp($props, 'conversation_list')[1]->id)->toBe($newer->id);
});

test('关闭线程不在首屏时仍可直接选中', function () {
    $target = null;

    for ($i = 0; $i < 60; $i++) {
        $conversation = createPaginationThreadConversation($this, [
            'status' => ConversationStatus::Closed,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'closed_at' => Carbon::parse('2026-06-17 12:00:00')->subMinutes($i),
            'last_message_at' => Carbon::parse('2026-06-17 12:00:00')->subMinutes($i),
        ]);

        if ($i === 55) {
            $target = $conversation->refresh();
        }
    }

    $targetThread = ConversationThread::requireForConversation($target);
    $props = app(ShowInboxAction::class)->handle(
        $this->user,
        new InboxFiltersData(view: InboxView::Closed, channel_id: null, assignee: null, search: null, important_only: false),
        threadId: (string) $targetThread->id,
    );

    expect(resolveInboxProp($props, 'conversation_list'))->toHaveCount(50)
        ->and(resolveInboxProp($props, 'current_thread_id'))->toBe((string) $targetThread->id)
        ->and(resolveInboxProp($props, 'selection')?->conversation->id)->toBe((string) $target?->id);
});
