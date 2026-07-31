<?php

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    DB::rollBack();
});

afterEach(function (): void {
    DB::table('conversation_threads')->delete();
    DB::table('conversations')->delete();
    DB::table('contacts')->delete();
    DB::table('channels')->delete();
    DB::beginTransaction();
});

test('完整身份会话的创建更新和删除必须处于事务中', function () {
    $contact = Contact::factory()->create();
    $channel = Channel::factory()->create();

    expect(fn () => Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create())->toThrow(LogicException::class);

    $conversation = DB::transaction(fn () => Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create());
    $thread = ConversationThread::requireForConversation($conversation);

    expect(fn () => $conversation->update(['last_message_at' => now()->addMinute()]))
        ->toThrow(LogicException::class);

    $conversation->refresh();

    expect(fn () => $conversation->delete())
        ->toThrow(LogicException::class)
        ->and(Conversation::query()->whereKey($conversation->id)->exists())->toBeTrue()
        ->and(ConversationThread::query()->whereKey($thread->id)->exists())->toBeTrue();
});

test('联系人重点标记与线程排序投影必须在同一事务中更新', function () {
    $contact = Contact::factory()->create(['is_important' => false]);
    $channel = Channel::factory()->create();
    $conversation = DB::transaction(fn () => Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create());
    $thread = ConversationThread::requireForConversation($conversation);

    expect(fn () => $contact->update(['is_important' => true]))
        ->toThrow(LogicException::class)
        ->and($contact->fresh()->is_important)->toBeFalse()
        ->and($thread->fresh()->is_important)->toBeFalse();

    DB::transaction(fn () => $contact->update(['is_important' => true]));

    expect($contact->fresh()->is_important)->toBeTrue()
        ->and($thread->fresh()->is_important)->toBeTrue();
});
