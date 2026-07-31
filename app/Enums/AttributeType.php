<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 自定义属性字段类型，影响表单渲染、校验和筛选。
 */
enum AttributeType: string implements LabeledEnum
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case SingleSelect = 'single_select';
    case MultiSelect = 'multi_select';

    public function label(): string
    {
        return match ($this) {
            self::Text => __('custom_attribute.types.text'),
            self::Textarea => __('custom_attribute.types.textarea'),
            self::Number => __('custom_attribute.types.number'),
            self::Date => __('custom_attribute.types.date'),
            self::Boolean => __('custom_attribute.types.boolean'),
            self::SingleSelect => __('custom_attribute.types.single_select'),
            self::MultiSelect => __('custom_attribute.types.multi_select'),
        };
    }

    public function usesOptions(): bool
    {
        return in_array($this, [self::SingleSelect, self::MultiSelect], true);
    }

    public function supportsFiltering(): bool
    {
        return in_array($this, [
            self::SingleSelect,
            self::Boolean,
            self::Date,
            self::Number,
        ], true);
    }

    /**
     * 返回支持作为筛选条件的属性类型。
     *
     * @return list<self>
     */
    public static function filterableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $type): bool => $type->supportsFiltering(),
        ));
    }
}
