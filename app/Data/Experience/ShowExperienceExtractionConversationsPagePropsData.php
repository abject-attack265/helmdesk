<?php

namespace App\Data\Experience;

use App\Data\KnowledgeBase\KnowledgeBaseSidebarData;
use Spatie\LaravelData\Data;

/**
 * 「提炼任务会话清单」页 props。
 * 由 ShowExperienceExtractionConversationsPageAction 返回给 resources/js/pages/experiences/Conversations.vue，
 * 只读展示该任务消费的会话列表，供逐个打开详情抽屉核对。
 */
class ShowExperienceExtractionConversationsPagePropsData extends Data
{
    public function __construct(
        public KnowledgeBaseSidebarData $sidebar,
        public ExperienceExtractionData $extraction,
        /** @var ListExtractableConversationItemData[] */
        public array $conversations,
    ) {}
}
