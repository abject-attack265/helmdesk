<?php

namespace App\Data\KnowledgeBase;

use App\Enums\KnowledgeBaseCategory;
use App\Models\KnowledgeBase;
use Spatie\LaravelData\Data;

/**
 * 知识库列表 / 详情下发的展示 Data，用于左侧 KB 列表行、KB 详情头部以及编辑弹窗回填。
 */
class KnowledgeBaseData extends Data
{
    /**
     * @param  array<int, KnowledgeGroupData>  $document_groups  当前知识库下的顶级分组（含 children）
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $avatar_id,
        public ?string $avatar_url,
        public ?string $description,
        public KnowledgeBaseCategory $category,
        public string $category_label,
        public ?string $created_at,
        public ?string $updated_at,
        public array $document_groups,
    ) {}

    /**
     * 从 Eloquent 模型构造展示 Data；调用方负责预加载 avatar / documentGroups.children 等关系，避免 N+1。
     */
    public static function fromModel(KnowledgeBase $knowledgeBase): self
    {
        $category = $knowledgeBase->category instanceof KnowledgeBaseCategory
            ? $knowledgeBase->category
            : KnowledgeBaseCategory::from((string) $knowledgeBase->category);

        $groups = [];
        if ($knowledgeBase->relationLoaded('documentGroups')) {
            $groups = $knowledgeBase->documentGroups
                ->map(fn ($g) => KnowledgeGroupData::fromModel($g))
                ->all();
        }

        return new self(
            id: (string) $knowledgeBase->id,
            name: $knowledgeBase->name,
            avatar_id: filled($knowledgeBase->avatar_id) ? (string) $knowledgeBase->avatar_id : null,
            avatar_url: $knowledgeBase->avatar?->full_url,
            description: filled($knowledgeBase->description) ? $knowledgeBase->description : null,
            category: $category,
            category_label: $category->label(),
            created_at: $knowledgeBase->created_at?->toIso8601String(),
            updated_at: $knowledgeBase->updated_at?->toIso8601String(),
            document_groups: $groups,
        );
    }
}
