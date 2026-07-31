<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 标签筛选匹配方式，用于 any / all 的包含和排除语义。
 */
enum TagMatchMode: string implements LabeledEnum
{
    case Any = 'any';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Any => __('contact.tag_match_modes.any'),
            self::All => __('contact.tag_match_modes.all'),
        };
    }
}
