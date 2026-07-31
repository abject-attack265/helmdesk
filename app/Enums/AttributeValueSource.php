<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 属性值来源，标记数据由人工、系统还是 AI 写入。
 */
enum AttributeValueSource: string implements LabeledEnum
{
    case Manual = 'manual';
    case Api = 'api';
    case Import = 'import';
    case Workflow = 'workflow';
    case Ai = 'ai';
    case Merge = 'merge';
    case Channel = 'channel';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('custom_attribute.sources.manual'),
            self::Api => __('custom_attribute.sources.api'),
            self::Import => __('custom_attribute.sources.import'),
            self::Workflow => __('custom_attribute.sources.workflow'),
            self::Ai => __('custom_attribute.sources.ai'),
            self::Merge => __('custom_attribute.sources.merge'),
            self::Channel => __('custom_attribute.sources.channel'),
        };
    }
}
