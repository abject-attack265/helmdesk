<?php

namespace App\Data\Tag;

use App\Enums\TagSource;
use App\Models\Tag;
use Spatie\LaravelData\Data;

/**
 * 联系人标签数据。
 * 由后端组装后传给 resources/js/pages/tags/Index.vue，用于页面展示、抽屉详情或局部交互状态。
 */
class ContactTagData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $color,
        public TagSource $source,
        public string $source_label,
        public bool $is_locked,
    ) {}

    public static function fromModel(Tag $tag): self
    {
        return new self(
            id: $tag->id,
            name: $tag->name,
            color: $tag->color,
            source: $tag->source,
            source_label: $tag->source->label(),
            is_locked: $tag->is_locked,
        );
    }
}
