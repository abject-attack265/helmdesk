<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * 处理 Fortify 重置密码后的用户密码更新。
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * 校验并重置用户忘记的密码。
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
