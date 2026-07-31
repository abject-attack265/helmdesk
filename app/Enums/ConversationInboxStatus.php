<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 收件箱状态，控制会话在收件箱里的处理队列。
 */
enum ConversationInboxStatus: string implements LabeledEnum
{
    case AiHandling = 'ai_handling';
    case TeammatePending = 'teammate_pending';
    case TeammateHandling = 'teammate_handling';

    public function label(): string
    {
        return match ($this) {
            self::AiHandling => __('conversation.inbox_statuses.ai_handling'),
            self::TeammatePending => __('conversation.inbox_statuses.teammate_pending'),
            self::TeammateHandling => __('conversation.inbox_statuses.teammate_handling'),
        };
    }
}
