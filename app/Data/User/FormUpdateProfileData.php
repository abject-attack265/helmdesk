<?php

namespace App\Data\User;

use App\Models\User;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 更新配置表单数据。
 * 来自 pages/settings/* 的编辑表单提交，后端用它校验并保存用户配置。
 */
class FormUpdateProfileData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore(request()->user()?->getAuthIdentifier()),
            ],
        ];
    }
}
