<?php

namespace App\Actions\Conversation;

use App\Enums\ConversationTimelineEntryType;
use App\Models\Conversation;
use App\Models\ConversationTimelineEntry;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 为消息或事件记录会话时间线索引。
 */
class RecordConversationTimelineEntryAction
{
    use AsAction;

    /**
     * 写入指向事实表的轻量时间线索引。
     */
    public function handle(
        ConversationTimelineEntryType $entryType,
        string $entryId,
        string $conversationId,
        Carbon $occurredAt,
    ): void {
        $conversation = Conversation::query()
            ->select(['id', 'contact_id'])
            ->whereKey($conversationId)
            ->firstOrFail();

        ConversationTimelineEntry::query()->create([
            'contact_id' => $conversation->contact_id,
            'conversation_id' => $conversation->id,
            'entry_type' => $entryType,
            'entry_id' => $entryId,
            'occurred_at' => $occurredAt,
        ]);
    }
}
