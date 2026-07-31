<?php

namespace App\Actions\User;

use App\Data\User\ShowTwoFactorAuthenticationSettingsPagePropsData;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示个人两步验证设置页面。
 */
class ShowTwoFactorAuthenticationSettingsPageAction
{
    use AsAction;

    /**
     * 校验两步验证功能状态并构建个人设置页数据。
     */
    public function handle(TwoFactorAuthenticationRequest $request): ShowTwoFactorAuthenticationSettingsPagePropsData
    {
        $request->ensureStateIsValid();

        return new ShowTwoFactorAuthenticationSettingsPagePropsData(
            twoFactorEnabled: $request->user()->hasEnabledTwoFactorAuthentication(),
            requiresConfirmation: Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
        );
    }

    /**
     * 渲染个人两步验证管理页。
     */
    public function asController(TwoFactorAuthenticationRequest $request): Response
    {
        return Inertia::render('settings/TwoFactor', $this->handle($request)->toArray());
    }
}
