<?php

namespace App\Observers;

use App\Actions\Conversation\RecordConversationTimelineEntryAction;
use App\Enums\ConversationTimelineEntryType;
use App\Models\ConversationEvent;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * 维护会话事件对应的时间线索引。
 */
class ConversationEventObserver
{
    /**
     * 要求事件与时间线索引在同一数据库事务中创建。
     */
    public function creating(ConversationEvent $event): void
    {
        if ($event->getConnection()->transactionLevel() > 0) {
            return;
        }

        Log::warning('会话事件与时间线索引写入缺少事务边界', [
            'conversation_id' => (string) $event->conversation_id,
            'event_type' => $event->type->value,
        ]);

        throw new LogicException('会话事件与时间线索引必须在同一数据库事务中创建。');
    }

    /**
     * 会话事件创建后写入时间线索引。
     */
    public function created(ConversationEvent $event): void
    {
        RecordConversationTimelineEntryAction::run(
            entryType: ConversationTimelineEntryType::Event,
            entryId: (string) $event->id,
            conversationId: (string) $event->conversation_id,
            occurredAt: $event->created_at,
        );
    }
}
