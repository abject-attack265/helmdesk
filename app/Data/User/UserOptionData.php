<?php

namespace App\Data\User;

use App\Models\User;
use Spatie\LaravelData\Data;

/**
 * 用户选项数据。
 * 传给应用页面的下拉框、筛选器或选择弹窗，字段保持前端选择控件需要的形状。
 */
class UserOptionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: (string) $user->id,
            name: $user->name,
            email: $user->email,
        );
    }
}
