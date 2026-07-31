<?php

namespace App\Data\Tag;

use App\Models\Tag;
use Spatie\LaravelData\Data;

/**
 * 标签选项数据。
 * 传给 resources/js/pages/tags/Index.vue 的下拉框、筛选器或选择弹窗，字段保持前端选择控件需要的形状。
 */
class TagOptionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $color,
    ) {}

    public static function fromModel(Tag $tag): self
    {
        return new self(
            id: $tag->id,
            name: $tag->name,
            color: $tag->color,
        );
    }
}
