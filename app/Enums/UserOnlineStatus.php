<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 成员接待状态，用于收件箱分配。
 */
enum UserOnlineStatus: int implements LabeledEnum
{
    case Online = 1;
    case Offline = 0;

    public function label(): string
    {
        return match ($this) {
            self::Online => __('user.online_statuses.online'),
            self::Offline => __('user.online_statuses.offline'),
        };
    }
}
