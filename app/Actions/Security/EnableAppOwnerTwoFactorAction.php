<?php

namespace App\Actions\Security;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 为应用所有者生成待确认的两步验证密钥与恢复码。
 */
class EnableAppOwnerTwoFactorAction
{
    use AsAction;

    /**
     * 为尚未配置密钥的应用所有者生成密钥与恢复码。
     */
    public function handle(User $user): void
    {
        $setupStarted = $user->two_factor_secret === null;

        app(EnableTwoFactorAuthentication::class)($user);

        if ($setupStarted) {
            Log::info('app_owner.two_factor.setup_started', [
                'user_id' => (string) $user->id,
            ]);
        }
    }

    /**
     * 生成密钥后返回配置页展示二维码。
     */
    public function asController(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user('web');

        if ($user->two_factor_confirmed_at !== null) {
            return redirect()->route('app.home');
        }

        $this->handle($user);

        return redirect()->route('app.owner.two-factor.setup');
    }
}
