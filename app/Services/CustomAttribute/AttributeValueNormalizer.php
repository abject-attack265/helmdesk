<?php

namespace App\Services\CustomAttribute;

use App\Enums\AttributeType;
use App\Models\AttributeDefinition;

/**
 * 自定义属性值的规范化与类型校验。
 *
 * 人工编辑（UpdateContactAttributeValuesAction）和 AI 自动提取
 * （GenerateContactAttributeValuesAction）共用同一套规则，保证落库口径一致。
 */
class AttributeValueNormalizer
{
    /**
     * 按属性类型把原始输入规范化成落库形态（数字转数值、布尔转 bool、多选去重）。
     */
    public static function normalize(AttributeDefinition $definition, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($definition->type) {
            AttributeType::Number => is_numeric($value) ? $value + 0 : $value,
            AttributeType::Boolean => is_bool($value) ? $value : ($value === 'true' ? true : ($value === 'false' ? false : $value)),
            AttributeType::MultiSelect => is_array($value) ? array_values(array_unique($value)) : $value,
            default => $value,
        };
    }

    /**
     * 判断规范化后的值是否为空（空值语义：删除该属性记录）。
     */
    public static function isEmpty(AttributeDefinition $definition, mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if ($definition->type === AttributeType::MultiSelect && is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    /**
     * 校验规范化后的值是否符合属性类型约束（含选项 code 是否在配置中）。
     */
    public static function isValid(AttributeDefinition $definition, mixed $value): bool
    {
        return match ($definition->type) {
            AttributeType::Text, AttributeType::Textarea => is_string($value),
            AttributeType::Number => is_numeric($value),
            AttributeType::Date => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1,
            AttributeType::Boolean => is_bool($value),
            AttributeType::SingleSelect => self::isValidOptionCode($definition, $value),
            AttributeType::MultiSelect => is_array($value) && self::areValidOptionCodes($definition, $value),
        };
    }

    /**
     * 校验单个选项 code 是否存在于属性配置中。
     */
    private static function isValidOptionCode(AttributeDefinition $definition, mixed $code): bool
    {
        if (! is_string($code)) {
            return false;
        }

        $options = $definition->config['options'] ?? [];

        return collect($options)->pluck('code')->contains($code);
    }

    /**
     * 校验多选值中的全部选项 code。
     *
     * @param  array<int, mixed>  $codes
     */
    private static function areValidOptionCodes(AttributeDefinition $definition, array $codes): bool
    {
        foreach ($codes as $code) {
            if (! self::isValidOptionCode($definition, $code)) {
                return false;
            }
        }

        return true;
    }
}
