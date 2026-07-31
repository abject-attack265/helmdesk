<?php

namespace App\Actions\User;

use App\Data\EnumOptionData;
use App\Data\User\ShowNotificationSettingsPagePropsData;
use App\Enums\NotificationSound;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示当前用户的通知设置页面。
 */
class ShowNotificationSettingsPageAction
{
    use AsAction;

    /**
     * 组装当前用户的通知偏好页面数据。
     */
    public function handle(User $user): ShowNotificationSettingsPagePropsData
    {
        return new ShowNotificationSettingsPagePropsData(
            preferences: $user->notificationPreferences(),
            sound_options: EnumOptionData::fromCases(NotificationSound::cases()),
        );
    }

    /**
     * 返回个人通知设置页面。
     */
    public function asController(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Notifications', $this->handle($user)->toArray());
    }
}
