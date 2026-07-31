<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 标签来源，记录标签是人工还是系统等来源创建。
 */
enum TagSource: string implements LabeledEnum
{
    case Manual = 'manual';
    case System = 'system';
    case Ai = 'ai';
    case Import = 'import';
    case Channel = 'channel';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('tag.sources.manual'),
            self::System => __('tag.sources.system'),
            self::Ai => __('tag.sources.ai'),
            self::Import => __('tag.sources.import'),
            self::Channel => __('tag.sources.channel'),
        };
    }
}
