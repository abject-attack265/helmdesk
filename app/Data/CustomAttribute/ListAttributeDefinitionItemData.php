<?php

namespace App\Data\CustomAttribute;

use App\Enums\AttributeType;
use App\Models\AttributeDefinition;
use Spatie\LaravelData\Data;

/**
 * 属性定义列表项数据。
 */
class ListAttributeDefinitionItemData extends Data
{
    public function __construct(
        public string $id,
        public string $key,
        public string $name,
        public ?string $description,
        public AttributeType $type,
        public string $type_label,
        /** @var array<string, mixed>|null */
        public ?array $config,
        public int $display_order,
        public bool $is_filterable,
        public bool $is_ai_readable,
        public bool $is_ai_writable,
        public int $usage_count,
        public ?string $deleted_at,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(AttributeDefinition $definition): self
    {
        return new self(
            id: $definition->id,
            key: $definition->key,
            name: $definition->name,
            description: $definition->description,
            type: $definition->type,
            type_label: $definition->type->label(),
            config: $definition->config,
            display_order: $definition->display_order,
            is_filterable: $definition->is_filterable,
            is_ai_readable: $definition->is_ai_readable,
            is_ai_writable: $definition->is_ai_writable,
            usage_count: (int) ($definition->contact_attribute_values_count ?? 0),
            deleted_at: $definition->deleted_at?->toIso8601String(),
            created_at: $definition->created_at?->toIso8601String() ?? '',
            updated_at: $definition->updated_at?->toIso8601String() ?? '',
        );
    }
}
