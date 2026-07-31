<?php

namespace App\Data\CustomAttribute;

use Spatie\LaravelData\Data;

/**
 * 联系人属性字段数据。
 * 由后端组装后传给 resources/js/pages/appSettings/datas/Attribute.vue，用于页面展示、抽屉详情或局部交互状态。
 */
class ContactAttributeFieldData extends Data
{
    public function __construct(
        public string $definition_id,
        public string $key,
        public string $name,
        public ?string $description,
        public string $type,
        public string $type_label,
        /** @var array<string, mixed>|null */
        public ?array $config,
        public mixed $value,
        public ?string $source,
        public ?string $source_label,
        public ?string $deleted_at,
        public bool $is_editable,
    ) {}
}
