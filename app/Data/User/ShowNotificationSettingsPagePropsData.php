<?php

namespace App\Data\User;

use App\Data\EnumOptionData;
use Spatie\LaravelData\Data;

/**
 * 个人通知设置页面 Props。
 * 由 ShowNotificationSettingsPageAction 返回给 resources/js/pages/settings/Notifications.vue。
 */
class ShowNotificationSettingsPagePropsData extends Data
{
    /**
     * @param  array<int, EnumOptionData>  $sound_options
     */
    public function __construct(
        public UserNotificationPreferencesData $preferences,
        public array $sound_options,
    ) {}
}
