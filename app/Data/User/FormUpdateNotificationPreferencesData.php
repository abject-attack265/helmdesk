<?php

namespace App\Data\User;

use App\Enums\NotificationSound;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 个人通知设置表单。
 * 来自 resources/js/pages/settings/Notifications.vue，用于保存当前用户的浏览器通知和声音提醒偏好。
 */
class FormUpdateNotificationPreferencesData extends Data
{
    public function __construct(
        public bool $browser_notifications_enabled,
        public bool $sound_enabled,
        public NotificationSound $sound,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'browser_notifications_enabled' => ['required', 'boolean'],
            'sound_enabled' => ['required', 'boolean'],
            'sound' => ['required', Rule::in(array_map(
                static fn (NotificationSound $sound): string => $sound->value,
                NotificationSound::cases(),
            ))],
        ];
    }
}
