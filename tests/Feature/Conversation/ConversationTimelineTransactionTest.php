<?php

use App\Enums\ConversationTimelineEntryType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationMessage;
use App\Models\ConversationTimelineEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    DB::rollBack();
});

afterEach(function (): void {
    DB::table('conversation_timeline_entries')->delete();
    DB::table('conversation_messages')->delete();
    DB::table('conversation_events')->delete();
    DB::table('conversations')->delete();
    DB::table('contacts')->delete();
    DB::beginTransaction();
});

test('会话消息和事件分别与对应时间线索引原子创建', function () {
    $contact = Contact::factory()->create();
    $conversation = Conversation::factory()->forContact($contact)->create();

    expect(fn () => ConversationMessage::factory()
        ->forConversation($conversation)
        ->create())->toThrow(LogicException::class);

    expect(fn () => ConversationEvent::factory()
        ->forConversation($conversation)
        ->create())->toThrow(LogicException::class);

    expect($conversation->fresh()->next_seq_no)->toBe(0)
        ->and(ConversationMessage::query()->count())->toBe(0)
        ->and(ConversationEvent::query()->count())->toBe(0)
        ->and(ConversationTimelineEntry::query()->count())->toBe(0);

    [$message, $event] = DB::transaction(function () use ($conversation): array {
        $message = ConversationMessage::factory()
            ->forConversation($conversation)
            ->create();
        $event = ConversationEvent::factory()
            ->forConversation($conversation)
            ->create();

        return [$message, $event];
    });

    $messageEntry = ConversationTimelineEntry::query()
        ->where('entry_type', ConversationTimelineEntryType::Message)
        ->where('entry_id', $message->id)
        ->firstOrFail();
    $eventEntry = ConversationTimelineEntry::query()
        ->where('entry_type', ConversationTimelineEntryType::Event)
        ->where('entry_id', $event->id)
        ->firstOrFail();

    expect($messageEntry->contact_id)->toBe($contact->id)
        ->and($messageEntry->conversation_id)->toBe($conversation->id)
        ->and($messageEntry->occurred_at->toIso8601String())->toBe($message->created_at->toIso8601String())
        ->and($eventEntry->contact_id)->toBe($contact->id)
        ->and($eventEntry->conversation_id)->toBe($conversation->id)
        ->and($eventEntry->occurred_at->toIso8601String())->toBe($event->created_at->toIso8601String())
        ->and(ConversationTimelineEntry::query()->where('conversation_id', $conversation->id)->count())->toBe(2);
});
