<?php

use App\Actions\Contact\MergeContactsAction;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('联系人渠道线程在多次会话生命周期之间保持稳定身份和当前状态投影', function () {
    $contact = Contact::factory()->create();
    $channel = Channel::factory()->create();
    $assignee = User::factory()->create();

    $older = Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->closed()
        ->create([
            'created_at' => Carbon::parse('2026-07-20 09:00:00'),
            'last_message_at' => Carbon::parse('2026-07-20 10:00:00'),
            'closed_at' => Carbon::parse('2026-07-20 11:00:00'),
        ])
        ->refresh();
    $newer = Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->closed()
        ->create([
            'created_at' => Carbon::parse('2026-07-21 09:00:00'),
            'last_message_at' => Carbon::parse('2026-07-21 10:00:00'),
            'closed_at' => Carbon::parse('2026-07-21 11:00:00'),
        ])
        ->refresh();

    $thread = ConversationThread::requireForConversation($older);
    expect(ConversationThread::requireForConversation($newer)->id)->toBe($thread->id)
        ->and($thread->current_conversation_id)->toBe($newer->id)
        ->and($thread->status)->toBe(ConversationStatus::Closed)
        ->and($thread->last_activity_at->toDateTimeString())->toBe('2026-07-21 11:00:00');

    $open = Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'created_at' => Carbon::parse('2026-07-22 09:00:00'),
            'last_message_at' => Carbon::parse('2026-07-22 10:00:00'),
        ])
        ->refresh();

    expect(ConversationThread::requireForConversation($open)->id)->toBe($thread->id)
        ->and($thread->fresh()->current_conversation_id)->toBe($open->id)
        ->and($thread->fresh()->status)->toBe(ConversationStatus::Open);

    $open->update([
        'assigned_user_id' => $assignee->id,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
        'last_message_at' => Carbon::parse('2026-07-22 12:00:00'),
    ]);

    $thread = $thread->fresh();
    expect($thread->assigned_user_id)->toBe($assignee->id)
        ->and($thread->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling)
        ->and($thread->last_activity_at->toDateTimeString())->toBe('2026-07-22 12:00:00');

    $open->update([
        'status' => ConversationStatus::Closed,
        'closed_at' => Carbon::parse('2026-07-22 13:00:00'),
    ]);

    expect($thread->fresh()->status)->toBe(ConversationStatus::Closed)
        ->and($thread->fresh()->last_activity_at->toDateTimeString())->toBe('2026-07-22 13:00:00');
});

test('开放会话被删除后线程回退到最近的关闭会话', function () {
    $contact = Contact::factory()->create();
    $channel = Channel::factory()->create();
    $closed = Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->closed()
        ->create([
            'created_at' => Carbon::parse('2026-07-20 09:00:00'),
            'closed_at' => Carbon::parse('2026-07-20 11:00:00'),
        ])
        ->refresh();
    $open = Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create(['created_at' => Carbon::parse('2026-07-21 09:00:00')])
        ->refresh();

    $threadId = (string) ConversationThread::requireForConversation($open)->id;
    expect(ConversationThread::query()->findOrFail($threadId)->current_conversation_id)->toBe($open->id);

    $open->delete();

    $thread = ConversationThread::query()->findOrFail($threadId);
    expect($thread->current_conversation_id)->toBe($closed->id)
        ->and($thread->status)->toBe(ConversationStatus::Closed);

    $closed->delete();

    expect(ConversationThread::query()->whereKey($threadId)->exists())->toBeFalse();
});

test('会话线程身份不能通过普通模型更新', function () {
    $contact = Contact::factory()->create();
    $channel = Channel::factory()->create();
    $otherChannel = Channel::factory()->create();
    $conversation = Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create()
        ->refresh();
    $threadId = (string) ConversationThread::requireForConversation($conversation)->id;

    expect(fn () => $conversation->update(['channel_id' => $otherChannel->id]))
        ->toThrow(LogicException::class);

    expect($conversation->fresh()->channel_id)->toBe($channel->id)
        ->and(ConversationThread::query()->findOrFail($threadId)->channel_id)->toBe($channel->id);
});

test('完整线程身份必须引用存在的联系人和渠道', function () {
    $channel = Channel::factory()->create();

    expect(fn () => Conversation::factory()->create([
        'contact_id' => '01K00000000000000000000000',
        'channel_id' => $channel->id,
    ]))->toThrow(LogicException::class);

    expect(Conversation::query()->count())->toBe(0)
        ->and(ConversationThread::query()->count())->toBe(0);
});

test('已关闭会话必须包含关闭时间', function () {
    $contact = Contact::factory()->create();
    $channel = Channel::factory()->create();

    expect(fn () => Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create([
            'status' => ConversationStatus::Closed,
            'closed_at' => null,
        ]))->toThrow(LogicException::class);

    expect(Conversation::query()->count())->toBe(0)
        ->and(ConversationThread::query()->count())->toBe(0);
});

test('线程投影以数据库中的会话最新状态为准', function () {
    $contact = Contact::factory()->create();
    $channel = Channel::factory()->create();
    $assignee = User::factory()->create();
    $conversation = Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create([
            'assigned_user_id' => null,
            'inbox_status' => ConversationInboxStatus::AiHandling,
        ])
        ->refresh();
    $staleConversation = Conversation::query()->findOrFail($conversation->id);

    DB::table('conversations')
        ->where('id', $conversation->id)
        ->update([
            'assigned_user_id' => $assignee->id,
            'inbox_status' => ConversationInboxStatus::TeammateHandling->value,
        ]);

    $staleConversation->update(['last_message_at' => now()->addMinute()]);

    $thread = ConversationThread::requireForConversation($conversation);
    expect($thread->assigned_user_id)->toBe($assignee->id)
        ->and($thread->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling);
});

test('联系人合并会合并同渠道线程并迁移全部历史会话归属', function () {
    $target = Contact::factory()->create();
    $merged = Contact::factory()->create();
    $sharedChannel = Channel::factory()->create();
    $otherChannel = Channel::factory()->create();

    $targetConversation = Conversation::factory()
        ->forContactChannel($target, $sharedChannel)
        ->closed()
        ->create([
            'created_at' => Carbon::parse('2026-07-20 09:00:00'),
            'updated_at' => Carbon::parse('2026-07-20 09:30:00'),
            'closed_at' => Carbon::parse('2026-07-20 11:00:00'),
        ])
        ->refresh();
    $mergedConversation = Conversation::factory()
        ->forContactChannel($merged, $sharedChannel)
        ->closed()
        ->create([
            'created_at' => Carbon::parse('2026-07-21 09:00:00'),
            'updated_at' => Carbon::parse('2026-07-21 09:30:00'),
            'closed_at' => Carbon::parse('2026-07-21 11:00:00'),
        ])
        ->refresh();
    $otherConversation = Conversation::factory()
        ->forContactChannel($merged, $otherChannel)
        ->closed()
        ->create()
        ->refresh();

    $sourceSharedThreadId = (string) ConversationThread::requireForConversation($mergedConversation)->id;
    $sourceOtherThreadId = (string) ConversationThread::requireForConversation($otherConversation)->id;

    MergeContactsAction::run($target->id, $merged->id);

    $sharedThread = ConversationThread::query()
        ->where('contact_id', $target->id)
        ->where('channel_id', $sharedChannel->id)
        ->firstOrFail();
    $movedOtherThread = ConversationThread::query()->findOrFail($sourceOtherThreadId);

    expect(ConversationThread::query()->whereKey($sourceSharedThreadId)->exists())->toBeFalse()
        ->and($sharedThread->current_conversation_id)->toBe($mergedConversation->id)
        ->and(ConversationThread::requireForConversation($targetConversation->fresh())->id)->toBe($sharedThread->id)
        ->and(ConversationThread::requireForConversation($mergedConversation)->id)->toBe($sharedThread->id)
        ->and($targetConversation->fresh()->updated_at->toDateTimeString())->toBe('2026-07-20 09:30:00')
        ->and($mergedConversation->fresh()->updated_at->toDateTimeString())->toBe('2026-07-21 09:30:00')
        ->and($movedOtherThread->contact_id)->toBe($target->id)
        ->and(ConversationThread::requireForConversation($otherConversation)->id)->toBe($movedOtherThread->id)
        ->and(Conversation::query()
            ->whereIn('id', [$targetConversation->id, $mergedConversation->id, $otherConversation->id])
            ->where('contact_id', $target->id)
            ->count())->toBe(3);
});
