<?php

namespace App\Data\Experience;

use App\Data\KnowledgeBase\KnowledgeBaseSidebarData;
use Spatie\LaravelData\Data;

/**
 * 「提炼任务经验结果」页 props。
 * 由 ShowExperienceExtractionResultsPageAction 返回给 resources/js/pages/experiences/Results.vue，
 * 承载该任务产出的候选经验（按状态筛选）与状态计数；采纳落库目标即任务绑定的问答库（extraction.knowledge_base）。
 */
class ShowExperienceExtractionResultsPagePropsData extends Data
{
    public function __construct(
        public KnowledgeBaseSidebarData $sidebar,
        public ExperienceExtractionData $extraction,
        /** @var ListExperienceCandidateItemData[] */
        public array $candidates,
        /** @var array<string, int> */
        public array $status_counts,
        public string $active_status,
    ) {}
}
