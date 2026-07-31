<?php

namespace App\Data\Inbox;

use App\Data\Conversation\ListConversationItemData;
use App\Data\EnumOptionData;
use App\Data\Tag\TagOptionData;
use App\Data\User\UserOptionData;
use App\Enums\InboxPane;
use App\Enums\InboxView;
use Spatie\LaravelData\Data;

/**
 * 收件箱页面 props 与前端生成类型契约。
 */
class ShowInboxPagePropsData extends Data
{
    /**
     * 承载收件箱线程列表、当前面板、筛选状态和统计数据。
     */
    public function __construct(
        public InboxView $current_view,
        public ?string $current_channel_id,
        public ?string $current_assignee,
        public ?string $current_search,
        public bool $current_important_only,
        public InboxPane $current_pane,
        public ?string $current_thread_id,
        /** @var EnabledWebChannelData[] */
        public array $enabled_web_channels,
        /** @var UserOptionData[] */
        public array $teammates,
        /** @var ListConversationItemData[] */
        public array $conversation_list,
        /** 下一页线程列表游标；为 null 表示已到末尾。 */
        public ?string $conversation_list_next_cursor,
        public ?InboxSelectionData $selection,
        /** @var TagOptionData[] */
        public array $available_contact_tags,
        /** @var TagOptionData[] */
        public array $available_conversation_tags,
        /** @var EnumOptionData[] */
        public array $reception_language_options,
        /** @var EnumOptionData[] */
        public array $reply_assistant_mode_options,
        /** @var EnumOptionData[] */
        public array $reply_polish_tone_options,
        public InboxTabCountsData $tab_counts,
    ) {}
}
