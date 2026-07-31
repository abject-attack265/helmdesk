<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 会话访客回复等待筛选状态，非等待仅作为筛选项，不作为列表徽标展示。
 */
enum ConversationVisitorReplyStatus: string implements LabeledEnum
{
    case Waiting = 'waiting';
    case NotWaiting = 'not_waiting';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => __('conversation.visitor_reply_statuses.waiting'),
            self::NotWaiting => __('conversation.visitor_reply_statuses.not_waiting'),
        };
    }
}
