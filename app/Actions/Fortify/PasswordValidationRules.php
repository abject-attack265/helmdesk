<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 提供 Fortify 用户创建和重置密码共用的密码规则。
 */
trait PasswordValidationRules
{
    /**
     * 返回 Fortify 共用密码校验规则。
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }
}
