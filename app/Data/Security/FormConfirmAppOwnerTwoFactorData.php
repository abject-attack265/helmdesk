<?php

namespace App\Data\Security;

use Spatie\LaravelData\Data;

/**
 * 应用所有者两步验证配置页的动态验证码确认表单。
 */
class FormConfirmAppOwnerTwoFactorData extends Data
{
    /**
     * 保存身份验证器生成的六位动态验证码。
     */
    public function __construct(
        public string $code,
    ) {}

    /**
     * 校验动态验证码的输入格式。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
        ];
    }
}
