<?php

namespace App\Data\Reception\ServiceScenario;

use App\Enums\KnowledgeBaseCategory;
use App\Models\KnowledgeBase;
use Spatie\LaravelData\Data;

/**
 * 接待方案级知识库多选项数据。
 * 由 ShowReceptionPlanIndexPageAction 一次性下发给 Index.vue，
 * 用于方案级知识库配置的多选组件。
 */
class PlanKnowledgeBaseOptionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?KnowledgeBaseCategory $category,
        public ?string $category_label,
    ) {}

    /**
     * 从应用可见的 KnowledgeBase 模型构造选项。
     */
    public static function fromModel(KnowledgeBase $knowledgeBase): self
    {
        $category = $knowledgeBase->category;

        return new self(
            id: (string) $knowledgeBase->id,
            name: $knowledgeBase->name,
            category: $category,
            category_label: $category?->label(),
        );
    }
}
