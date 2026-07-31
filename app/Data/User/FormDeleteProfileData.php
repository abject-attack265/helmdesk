<?php

namespace App\Data\User;

use Spatie\LaravelData\Data;

/**
 * 删除配置表单数据。
 * 来自 pages/settings/* 的确认弹窗提交，用于校验删除动作需要的字段。
 */
class FormDeleteProfileData extends Data
{
    public function __construct(
        public string $password,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'password' => ['required', 'current_password'],
        ];
    }
}
