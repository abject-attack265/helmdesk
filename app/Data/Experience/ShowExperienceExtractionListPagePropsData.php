<?php

namespace App\Data\Experience;

use App\Data\EnumOptionData;
use App\Data\KnowledgeBase\KnowledgeBaseSidebarData;
use App\Data\SimplePaginationData;
use App\Enums\ExperienceExtractionStatus;
use Spatie\LaravelData\Data;

/**
 * 「经验提炼」任务列表页 props。
 * 由 ShowExperienceExtractionListPageAction 返回给 resources/js/pages/experiences/Index.vue，
 * 承载绑定的问答知识库上下文、分页任务列表、状态筛选项与当前筛选状态。
 */
class ShowExperienceExtractionListPagePropsData extends Data
{
    public function __construct(
        public KnowledgeBaseSidebarData $sidebar,
        public ExperienceKnowledgeBaseData $knowledge_base,
        /** @var ListExperienceExtractionItemData[] */
        public array $extractions,
        public SimplePaginationData $extractions_pagination,
        /** @var EnumOptionData[] */
        public array $status_options,
        public ?ExperienceExtractionStatus $current_status,
    ) {}
}
