<?php

namespace App\Actions\Security;

use App\Data\Security\ShowAppOwnerTwoFactorSetupPagePropsData;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Fortify;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示应用所有者的两步验证初始化页。
 */
class ShowAppOwnerTwoFactorSetupPageAction
{
    use AsAction;

    /**
     * 构建初始化页数据，确认完成后不返回扫码密钥。
     */
    public function handle(User $user): ShowAppOwnerTwoFactorSetupPagePropsData
    {
        if ($user->two_factor_secret === null) {
            return new ShowAppOwnerTwoFactorSetupPagePropsData(
                two_factor_enabled: false,
                two_factor_confirmed: false,
                qr_code_svg: null,
                manual_setup_key: null,
            );
        }

        $confirmed = $user->two_factor_confirmed_at !== null;

        return new ShowAppOwnerTwoFactorSetupPagePropsData(
            two_factor_enabled: true,
            two_factor_confirmed: $confirmed,
            qr_code_svg: $confirmed ? null : $user->twoFactorQrCodeSvg(),
            manual_setup_key: $confirmed ? null : Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
        );
    }

    /**
     * 已确认两步验证的应用所有者直接进入应用，否则渲染配置页。
     */
    public function asController(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user('web');

        if ($user->two_factor_confirmed_at !== null) {
            return redirect()->route('app.home');
        }

        return Inertia::render('app/OwnerTwoFactorSetup', $this->handle($user)->toArray());
    }
}
