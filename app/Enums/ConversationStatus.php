<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 会话生命周期状态，表示会话打开或关闭。
 */
enum ConversationStatus: string implements LabeledEnum
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('conversation.statuses.open'),
            self::Closed => __('conversation.statuses.closed'),
        };
    }
}
