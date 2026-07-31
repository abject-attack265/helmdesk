<?php

namespace App\Data\Conversation;

use App\Data\Conversation\ChannelContext\TelegramConversationChannelContextData;
use App\Data\Conversation\ChannelContext\WebConversationChannelContextData;
use App\Data\Conversation\ChannelContext\WechatOfficialAccountConversationChannelContextData;
use App\Enums\ChannelType;
use App\Enums\ConversationEntryMode;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationSource;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\Tag;
use Spatie\LaravelData\Data;

/**
 * 会话摘要数据。
 * 由后端组装后传给 resources/js/pages/contacts/Conversation.vue、ConversationDetailDrawer.vue 和 inbox/InboxStitchedTimeline.vue，用于页面展示、抽屉详情或局部交互状态。
 */
class ConversationSummaryData extends Data
{
    /**
     * 创建会话摘要数据。
     */
    public function __construct(
        public string $id,
        public ConversationStatus $status,
        public string $status_label,
        public ConversationInboxStatus $inbox_status,
        public string $inbox_status_label,
        public bool $waiting_for_visitor_reply,
        public ?string $waiting_for_visitor_reply_label,
        public ConversationSource $source,
        public string $source_label,
        public ?string $subject,
        public ?string $summary,
        public ?string $summary_locale,
        /** @var array<string, array<string, mixed>>|null */
        public ?array $summary_translations,
        public ?string $summary_generated_at,
        /** @var array<string, mixed>|null */
        public ?array $ai_context,
        public ?string $last_message_preview,
        public ?string $last_message_at,
        public ?string $closed_at,
        public string $created_at,
        public ?ConversationEntryMode $entry_mode,
        public ?string $entry_mode_label,
        public ?string $channel_id,
        public ?string $assigned_user_id,
        public ?string $assigned_user_name,
        public string $visitor_locale,
        public int $message_count,
        /** @var ConversationTagData[] */
        public array $tags = [],
        public ?ChannelType $channel_type = null,
        public ?string $channel_type_label = null,
        public ?string $channel_name = null,
        public WebConversationChannelContextData|TelegramConversationChannelContextData|WechatOfficialAccountConversationChannelContextData|null $channel_context = null,
    ) {}

    /**
     * 从会话模型创建摘要数据。
     */
    public static function fromModel(Conversation $conversation): self
    {
        return new self(
            id: $conversation->id,
            status: $conversation->status,
            status_label: $conversation->status->label(),
            inbox_status: $conversation->inbox_status,
            inbox_status_label: $conversation->inbox_status->label(),
            waiting_for_visitor_reply: (bool) $conversation->waiting_for_visitor_reply,
            waiting_for_visitor_reply_label: $conversation->waitingForVisitorReplyLabel(),
            source: $conversation->source,
            source_label: $conversation->source->label(),
            subject: $conversation->subject,
            summary: $conversation->summary,
            summary_locale: $conversation->summary_locale,
            summary_translations: $conversation->summary_translations,
            summary_generated_at: $conversation->summary_generated_at?->toIso8601String(),
            ai_context: $conversation->ai_context,
            last_message_preview: $conversation->last_message_preview,
            last_message_at: $conversation->last_message_at?->toIso8601String(),
            closed_at: $conversation->closed_at?->toIso8601String(),
            created_at: $conversation->created_at?->toIso8601String() ?? '',
            entry_mode: $conversation->entry_mode,
            entry_mode_label: $conversation->entry_mode?->label(),
            channel_id: $conversation->channel_id,
            assigned_user_id: $conversation->assigned_user_id,
            assigned_user_name: $conversation->assignedUser?->name,
            visitor_locale: $conversation->visitor_locale,
            message_count: (int) ($conversation->messages_count ?? $conversation->display_message_count ?? 0),
            tags: $conversation->relationLoaded('tags')
                ? $conversation->tags->map(fn (Tag $tag) => ConversationTagData::fromModel($tag))->all()
                : [],
            channel_type: $conversation->relationLoaded('channel') ? $conversation->channel?->type : null,
            channel_type_label: $conversation->relationLoaded('channel') ? $conversation->channel?->type->label() : null,
            channel_name: $conversation->relationLoaded('channel') ? $conversation->channel?->name : null,
            channel_context: $conversation->channel_context,
        );
    }
}
