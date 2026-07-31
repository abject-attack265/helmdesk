<?php

namespace App\Actions\Security;

use App\Http\Middleware\EnsureAppOwnerTwoFactorConfirmed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 暂时跳过应用所有者的两步验证配置，本次会话结束后重新引导。
 */
class SkipAppOwnerTwoFactorSetupAction
{
    use AsAction;

    /**
     * 在当前会话记录应用所有者暂时跳过两步验证配置。
     */
    public function handle(Request $request): void
    {
        $request->session()->put(EnsureAppOwnerTwoFactorConfirmed::SKIP_SESSION_KEY, true);

        Log::warning('app_owner.two_factor.setup_skipped', [
            'user_id' => (string) $request->user('web')->id,
        ]);
    }

    /**
     * 记录跳过状态后进入应用首页。
     */
    public function asController(Request $request): RedirectResponse
    {
        $this->handle($request);

        return redirect()->route('app.home');
    }
}
