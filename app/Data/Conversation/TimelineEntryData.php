<?php

namespace App\Data\Conversation;

use Spatie\LaravelData\Data;

/**
 * 时间线条目数据。
 * 由后端组装后传给 resources/js/pages/contacts/Conversation.vue、ConversationDetailDrawer.vue 和 inbox/InboxStitchedTimeline.vue，用于页面展示、抽屉详情或局部交互状态。
 *
 * AI 角色消息 / AI 事件不带 actor 主键，前端按 reception plan persona 渲染 AI 身份。
 */
class TimelineEntryData extends Data
{
    /**
     * 创建会话详情时间线条目。
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $subtype,
        public string $subtype_label,
        public ?string $role,
        public ?string $kind,
        public ?string $event_type,
        public ?string $actor_user_id,
        public ?string $sender_name,
        public ?string $sender_avatar_url,
        public ?string $content,
        public ?string $content_locale,
        /** @var array<string, mixed>|null */
        public ?array $payload,
        public string $occurred_at,
        public ?int $seq_no = null,
        public ?string $delivery_status = null,
        public ?string $quoted_message_id = null,
        public ?QuotedMessageData $quoted_message = null,
        public ?string $recalled_at = null,
        /**
         * 已撤回消息的原始文本，仅在 viewer 即可重新编辑者（自己发的 teammate 消息或同事维护下的 AI 消息）时下发。
         */
        public ?string $recalled_content = null,
        /**
         * 事件条目的客服可读展示数据；消息条目为空。
         */
        public ?ConversationEventDisplayData $event_display = null,
        /**
         * 产生该 AI 回复的接待轮次 ID（仅 AI 接待回复消息），可用于在应用 AI 调用日志中反查推理过程。
         */
        public ?string $turn_id = null,
    ) {}
}
