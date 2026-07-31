<?php

namespace App\Data\Channel\Web;

use App\Enums\AttributeType;
use App\Models\AttributeDefinition;
use Spatie\LaravelData\Data;

/**
 * 渠道参数映射页面下拉里展示的可写入自定义属性选项。
 *
 * 配合 ApplyVisitorQueryParamsAction：只有 is_api_writable=true、type 在白名单内的
 * AttributeDefinition 才允许被渠道参数映射写入。
 */
class WritableAttributeDefinitionOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
        public AttributeType $type,
        public string $type_label,
    ) {}

    public static function fromModel(AttributeDefinition $definition): self
    {
        return new self(
            value: (string) $definition->key,
            label: (string) $definition->name,
            type: $definition->type,
            type_label: $definition->type->label(),
        );
    }
}
