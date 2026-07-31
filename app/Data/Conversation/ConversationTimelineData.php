<?php

namespace App\Data\Conversation;

use Spatie\LaravelData\Data;

/**
 * 会话时间线数据。
 * 由后端组装后传给 resources/js/pages/contacts/Conversation.vue、ConversationDetailDrawer.vue 和 inbox/InboxStitchedTimeline.vue，用于页面展示、抽屉详情或局部交互状态。
 */
class ConversationTimelineData extends Data
{
    public function __construct(
        /** @var TimelineEntryData[] */
        public array $items,
        public ?string $next_cursor,
    ) {}
}
