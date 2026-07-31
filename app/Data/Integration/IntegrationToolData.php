<?php

namespace App\Data\Integration;

use App\Models\IntegrationTool;
use Spatie\LaravelData\Data;

/**
 * 集成列表工具明细使用的展示数据。
 */
class IntegrationToolData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        /** @var array<string, mixed>|null */
        public ?array $input_schema,
        /** @var array<string, mixed>|null */
        public ?array $annotations,
        public bool $is_enabled,
        public ?string $last_seen_at,
        public ?string $removed_at,
    ) {}

    /**
     * 从模型构造展示数据并将时间转换为 ISO 字符串。
     */
    public static function fromModel(IntegrationTool $tool): self
    {
        return new self(
            id: (string) $tool->id,
            name: (string) $tool->name,
            description: $tool->description,
            input_schema: $tool->input_schema,
            annotations: $tool->annotations,
            is_enabled: (bool) $tool->is_enabled,
            last_seen_at: $tool->last_seen_at?->toIso8601String(),
            removed_at: $tool->removed_at?->toIso8601String(),
        );
    }
}
