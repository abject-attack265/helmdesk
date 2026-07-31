<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 会话来源，区分渠道、手动创建等入口。
 */
enum ConversationSource: string implements LabeledEnum
{
    case Manual = 'manual';
    case Channel = 'channel';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('conversation.sources.manual'),
            self::Channel => __('conversation.sources.channel'),
        };
    }
}
