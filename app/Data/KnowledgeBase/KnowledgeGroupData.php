<?php

namespace App\Data\KnowledgeBase;

use App\Models\KnowledgeGroup;
use Spatie\LaravelData\Data;

/**
 * 知识库分组展示 Data，用于左侧 KB 树渲染分组节点和创建分组弹窗的「上级分组」选项。
 */
class KnowledgeGroupData extends Data
{
    /**
     * @param  array<int, KnowledgeGroupData>  $children  二级子分组（最多两级，叶子节点为空数组）
     */
    public function __construct(
        public string $id,
        public string $knowledge_base_id,
        public ?string $parent_id,
        public string $name,
        public bool $is_default,
        public int $sort_order,
        public array $children,
    ) {}

    /**
     * 从 Eloquent 模型构造展示 Data；调用方负责预加载 children 关系，叶子节点不加载更深层级。
     */
    public static function fromModel(KnowledgeGroup $group): self
    {
        return new self(
            id: (string) $group->id,
            knowledge_base_id: (string) $group->knowledge_base_id,
            parent_id: filled($group->parent_id) ? (string) $group->parent_id : null,
            name: $group->is_default ? __('knowledge_base.groups.default_group') : $group->name,
            is_default: (bool) $group->is_default,
            sort_order: $group->sort_order,
            children: $group->relationLoaded('children')
                ? $group->children->map(fn (KnowledgeGroup $child) => self::fromModel($child))->all()
                : [],
        );
    }
}
