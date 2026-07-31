<?php

namespace App\Data\User;

use Illuminate\Validation\Rules\Password;
use Spatie\LaravelData\Data;

/**
 * 更新密码表单数据。
 * 来自 pages/settings/* 的编辑表单提交，后端用它校验并保存用户配置。
 */
class FormUpdatePasswordData extends Data
{
    public function __construct(
        public string $current_password,
        public string $password,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];
    }
}
