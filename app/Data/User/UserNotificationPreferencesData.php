<?php

namespace App\Data\User;

use App\Enums\NotificationSound;
use Spatie\LaravelData\Data;

/**
 * 用户个人通知偏好。
 * 由 settings/Notifications 页面和后台全局提醒逻辑共同消费。
 */
class UserNotificationPreferencesData extends Data
{
    public function __construct(
        public bool $browser_notifications_enabled = true,
        public bool $sound_enabled = true,
        public NotificationSound $sound = NotificationSound::Pop,
    ) {}
}
