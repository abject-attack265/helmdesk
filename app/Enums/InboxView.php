<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 收件箱视图类型，决定当前会话列表的筛选入口。
 */
enum InboxView: string implements LabeledEnum
{
    case Pending = 'pending';
    case Ai = 'ai';
    case Mine = 'mine';
    case Teammates = 'teammates';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('inbox.views.pending'),
            self::Ai => __('inbox.views.ai'),
            self::Mine => __('inbox.views.mine'),
            self::Teammates => __('inbox.views.teammates'),
            self::Closed => __('inbox.views.closed'),
        };
    }
}
