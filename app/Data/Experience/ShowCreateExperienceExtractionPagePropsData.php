<?php

namespace App\Data\Experience;

use App\Data\KnowledgeBase\KnowledgeBaseSidebarData;
use App\Data\SimplePaginationData;
use App\Data\User\UserOptionData;
use Spatie\LaravelData\Data;

/**
 * 「创建提炼任务」页 props。
 * 由 ShowCreateExperienceExtractionPageAction 返回给 resources/js/pages/experiences/Create.vue，
 * 承载绑定的问答知识库上下文，以及时间窗口内按坐席/关键词筛选出的可勾选联系人分页列表。
 */
class ShowCreateExperienceExtractionPagePropsData extends Data
{
    public function __construct(
        public KnowledgeBaseSidebarData $sidebar,
        public ExperienceKnowledgeBaseData $knowledge_base,
        /** @var ListExtractableContactItemData[] */
        public array $selectable_contacts,
        public SimplePaginationData $selectable_pagination,
        /** 归一化后的会话时间窗口，前端日期输入框直接回显这里的值。 */
        public ExperienceExtractionWindowData $window,
        /** 窗口最大跨度（天），前端据此约束日期选择。 */
        public int $max_window_days,
        /** 单次运行允许送入的会话数上限，前端按勾选联系人的会话数累计并拦截超额提交。 */
        public int $max_conversations,
        public ?string $filter_teammate_user_id,
        public ?string $filter_search,
        /** @var UserOptionData[] */
        public array $teammate_options,
        public bool $has_running_extraction,
    ) {}
}
