<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 后台新消息提示音类型，用于个人通知设置。
 */
enum NotificationSound: string implements LabeledEnum
{
    case Pop = 'pop';
    case Note = 'note';
    case Rebound = 'rebound';
    case Ding = 'ding';

    public function label(): string
    {
        return match ($this) {
            self::Pop => __('user.notification_sounds.pop'),
            self::Note => __('user.notification_sounds.note'),
            self::Rebound => __('user.notification_sounds.rebound'),
            self::Ding => __('user.notification_sounds.ding'),
        };
    }
}
