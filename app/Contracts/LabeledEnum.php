<?php

namespace App\Contracts;

/**
 * 带前端展示文案的枚举约定。
 */
interface LabeledEnum
{
    public function label(): string;
}
