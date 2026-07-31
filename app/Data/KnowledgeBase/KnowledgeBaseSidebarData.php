<?php

namespace App\Data\KnowledgeBase;

use App\Data\EnumOptionData;
use Spatie\LaravelData\Data;

/**
 * 知识库资源管理器侧边栏数据（知识库 + 分组树、可创建的分类选项），
 * 供 resources/js/pages/knowledgeBase/KnowledgeBaseExplorerSidebar.vue 在
 * 知识库页之外的页面（如经验提炼）复用左侧导航。
 */
class KnowledgeBaseSidebarData extends Data
{
    public function __construct(
        /** @var KnowledgeBaseData[] */
        public array $knowledge_base_list,
        /** @var EnumOptionData[] */
        public array $category_options,
    ) {}
}
