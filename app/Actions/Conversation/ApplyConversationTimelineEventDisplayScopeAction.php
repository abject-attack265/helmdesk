<?php

namespace App\Actions\Conversation;

use App\Enums\ConversationEventType;
use Illuminate\Database\Query\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 限定客服时间线可展示的会话事件类型。
 */
class ApplyConversationTimelineEventDisplayScopeAction
{
    use AsAction;

    /**
     * 排除接待轮次开始与结束记录。
     */
    public function handle(Builder $query): void
    {
        $query->whereNotIn('type', [
            ConversationEventType::ReceptionTurnStarted,
            ConversationEventType::ReceptionTurnEnded,
        ]);
    }
}
