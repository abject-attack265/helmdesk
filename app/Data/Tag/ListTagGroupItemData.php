<?php

namespace App\Data\Tag;

use App\Enums\TagScope;
use App\Models\TagGroup;
use Spatie\LaravelData\Data;

/**
 * 标签组列表项数据。
 * 用于标签管理页 resources/js/pages/tags/Index.vue 按 scope 分区后，渲染每个标签组及其组内标签 chip。
 */
class ListTagGroupItemData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public TagScope $scope,
        public string $scope_label,
        public int $sort_order,
        /** @var ListTagItemData[] */
        public array $tags,
    ) {}

    /**
     * 由标签组模型构建；tags 取该组下已加载的标签集合。
     */
    public static function fromModel(TagGroup $group): self
    {
        $tags = $group->relationLoaded('tags') ? $group->tags : collect();

        return new self(
            id: $group->id,
            name: $group->name,
            scope: $group->scope,
            scope_label: $group->scope->label(),
            sort_order: $group->sort_order,
            tags: $tags
                ->map(fn ($tag) => ListTagItemData::fromModel($tag))
                ->all(),
        );
    }
}
