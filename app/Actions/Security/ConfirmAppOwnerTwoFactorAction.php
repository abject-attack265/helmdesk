<?php

namespace App\Actions\Security;

use App\Data\Security\FormConfirmAppOwnerTwoFactorData;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 校验动态验证码并确认应用所有者的两步验证配置。
 */
class ConfirmAppOwnerTwoFactorAction
{
    use AsAction;

    /**
     * 校验 TOTP 验证码并把错误转换到本地化 code 字段。
     */
    public function handle(User $user, string $code): void
    {
        try {
            app(ConfirmTwoFactorAuthentication::class)($user, $code);
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'code' => [__('two_factor.invalid_code')],
            ]);
        }

        Log::info('app_owner.two_factor.confirmed', [
            'user_id' => (string) $user->id,
        ]);
    }

    /**
     * 确认完成后进入应用首页。
     */
    public function asController(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user('web');

        if ($user->two_factor_confirmed_at !== null) {
            return redirect()->route('app.home');
        }

        $data = FormConfirmAppOwnerTwoFactorData::from($request);

        $this->handle($user, $data->code);

        return redirect()->route('app.home');
    }
}
