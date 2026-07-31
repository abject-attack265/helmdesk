<?php

namespace App\Data\Conversation;

use App\Data\EnumOptionData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationThread;
use LogicException;
use Spatie\LaravelData\Data;

/**
 * 收件箱左侧线程列表项，供 Inbox.vue 展示。
 */
class ListConversationItemData extends Data
{
    /**
     * 承载线程当前会话、联系人、渠道和消息预览。
     */
    public function __construct(
        public string $id,
        public string $thread_id,
        public EnumOptionData $status,
        public EnumOptionData $inbox_status,
        public bool $waiting_for_visitor_reply,
        public ?string $waiting_for_visitor_reply_label,
        public EnumOptionData $source,
        public ?string $subject,
        public ?string $summary,
        public ?string $last_message_preview,
        /** @var array<string, string> */
        public array $last_message_translation_previews,
        public bool $last_message_can_translate,
        public ?string $last_message_at,
        public ?string $closed_at,
        public string $created_at,
        public string $contact_id,
        public ?string $contact_name,
        public ?string $contact_avatar_url,
        public ?string $contact_primary_email,
        public ?string $contact_primary_phone,
        public bool $contact_is_important,
        public ?string $reception_plan_version_id,
        public ?string $reception_plan_name,
        public ?int $reception_plan_version_number,
        public ?string $assigned_user_id,
        public ?string $assigned_user_name,
        public ChannelType $channel_type,
        public string $channel_type_label,
        public string $channel_name,
        public int $unread_count,
    ) {}

    /**
     * 从已加载当前会话关系的线程生成列表项。
     */
    public static function fromModel(ConversationThread $thread, int $unreadCount): self
    {
        if (! $thread->relationLoaded('currentConversation')) {
            throw new LogicException("收件箱线程 {$thread->id} 未加载当前代表会话。");
        }

        $conversation = $thread->getRelation('currentConversation');
        if (! $conversation instanceof Conversation) {
            throw new LogicException("收件箱线程 {$thread->id} 缺少当前代表会话。");
        }

        foreach (['contact', 'channel', 'assignedUser', 'receptionPlanVersion', 'latestMessage'] as $relation) {
            if (! $conversation->relationLoaded($relation)) {
                throw new LogicException("收件箱线程 {$thread->id} 的当前会话未加载 {$relation} 关系。");
            }
        }

        $contact = $conversation->getRelation('contact');
        if (! $contact instanceof Contact) {
            throw new LogicException("收件箱线程 {$thread->id} 的当前会话缺少联系人。");
        }

        $channel = $conversation->getRelation('channel');
        if (! $channel instanceof Channel) {
            throw new LogicException("收件箱线程 {$thread->id} 的当前会话缺少渠道。");
        }

        $planVersion = $conversation->getRelation('receptionPlanVersion');
        if ($planVersion !== null && ! $planVersion->relationLoaded('plan')) {
            throw new LogicException("收件箱线程 {$thread->id} 的当前会话未加载接待方案关系。");
        }

        $plan = $planVersion?->plan;

        return new self(
            id: $conversation->id,
            thread_id: (string) $thread->id,
            status: EnumOptionData::fromEnum($conversation->status),
            inbox_status: EnumOptionData::fromEnum($conversation->inbox_status),
            waiting_for_visitor_reply: (bool) $conversation->waiting_for_visitor_reply,
            waiting_for_visitor_reply_label: $conversation->waitingForVisitorReplyLabel(),
            source: EnumOptionData::fromEnum($conversation->source),
            subject: $conversation->subject,
            summary: $conversation->summary,
            last_message_preview: $conversation->last_message_preview,
            last_message_translation_previews: self::lastMessageTranslationPreviews($conversation),
            last_message_can_translate: self::lastMessageCanTranslate($conversation),
            last_message_at: $conversation->last_message_at?->toIso8601String(),
            closed_at: $conversation->closed_at?->toIso8601String(),
            created_at: $conversation->created_at->toIso8601String(),
            contact_id: (string) $contact->id,
            contact_name: $contact->name,
            contact_avatar_url: $contact->hasCustomAvatar() ? $contact->avatar_url : null,
            contact_primary_email: $contact->primary_email,
            contact_primary_phone: $contact->primary_phone,
            contact_is_important: $thread->is_important,
            reception_plan_version_id: filled($conversation->reception_plan_version_id) ? (string) $conversation->reception_plan_version_id : null,
            reception_plan_name: filled($plan?->name) ? (string) $plan->name : null,
            reception_plan_version_number: $planVersion?->version_number !== null ? (int) $planVersion->version_number : null,
            assigned_user_id: $conversation->assigned_user_id,
            assigned_user_name: $conversation->assignedUser?->name,
            channel_type: $channel->type,
            channel_type_label: $channel->type->label(),
            channel_name: $channel->name,
            unread_count: $unreadCount,
        );
    }

    /**
     * 生成最后一条消息的各语言译文预览。
     *
     * @return array<string, string>
     */
    private static function lastMessageTranslationPreviews(Conversation $conversation): array
    {
        $message = $conversation->latestMessage;

        if (! $message instanceof ConversationMessage || $message->isRecalled()) {
            return [];
        }

        $previews = [];
        foreach ($message->payload['translations'] ?? [] as $locale => $translation) {
            $text = is_array($translation) ? ($translation['text'] ?? null) : null;
            if (is_string($locale) && is_string($text) && $text !== '') {
                $previews[$locale] = Conversation::messagePreview($text);
            }
        }

        return $previews;
    }

    /**
     * 判断最后一条消息是否符合翻译条件。
     */
    private static function lastMessageCanTranslate(Conversation $conversation): bool
    {
        $message = $conversation->latestMessage;

        return $message instanceof ConversationMessage
            && $message->isEligibleForTranslation();
    }
}
