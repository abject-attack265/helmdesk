<?php

namespace App\Actions\User;

use App\Data\User\FormUpdateNotificationPreferencesData;
use App\Data\User\UserNotificationPreferencesData;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新当前用户的通知设置。
 */
class UpdateNotificationSettingsAction
{
    use AsAction;

    /**
     * 保存当前用户的通知偏好。
     */
    public function handle(User $user, FormUpdateNotificationPreferencesData $data): void
    {
        $user->forceFill([
            'notification_preferences' => UserNotificationPreferencesData::from($data->toArray()),
        ])->save();
    }

    /**
     * 接收个人通知设置表单提交并返回原页面。
     */
    public function asController(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->handle($user, FormUpdateNotificationPreferencesData::from($request));

        return back();
    }
}
