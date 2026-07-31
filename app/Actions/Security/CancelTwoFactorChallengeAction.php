<?php

namespace App\Actions\Security;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 取消待完成的两步验证挑战并返回登录页切换账号。
 */
class CancelTwoFactorChallengeAction
{
    use AsAction;

    /**
     * 清除 Fortify 暂存的挑战账号与记住登录标记。
     */
    public function handle(Request $request): void
    {
        $request->session()->forget(['login.id', 'login.remember']);
    }

    /**
     * 取消挑战后重定向到登录页。
     */
    public function asController(Request $request): RedirectResponse
    {
        $this->handle($request);

        return redirect()->route('login');
    }
}
