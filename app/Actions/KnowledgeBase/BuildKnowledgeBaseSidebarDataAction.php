<?php

namespace App\Actions\KnowledgeBase;

use App\Data\EnumOptionData;
use App\Data\KnowledgeBase\KnowledgeBaseData;
use App\Data\KnowledgeBase\KnowledgeBaseSidebarData;
use App\Enums\KnowledgeBaseCategory;
use App\Models\KnowledgeBase;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 组装知识库资源管理器侧边栏数据：应用下全部知识库（含分组树）与可创建的分类选项。
 * 供知识库页之外仍需左侧资源树的页面（如经验提炼各页）复用。
 */
class BuildKnowledgeBaseSidebarDataAction
{
    use AsAction;

    /**
     * 查询应用下所有知识库及其分组树并转换为侧边栏 Data。
     */
    public function handle(): KnowledgeBaseSidebarData
    {
        $knowledgeBases = KnowledgeBase::query()
            ->with([
                'avatar',
                'documentGroups.children',
                'documentGroups.children.children',
            ])

            ->oldest('created_at')
            ->oldest('id')
            ->get();

        return new KnowledgeBaseSidebarData(
            knowledge_base_list: $knowledgeBases
                ->map(static fn (KnowledgeBase $kb): KnowledgeBaseData => KnowledgeBaseData::fromModel($kb))
                ->all(),
            category_options: EnumOptionData::fromCases(KnowledgeBaseCategory::creatableCases()),
        );
    }
}
