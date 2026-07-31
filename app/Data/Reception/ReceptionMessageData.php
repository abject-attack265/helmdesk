<?php

namespace App\Data\Reception;

use App\Data\Conversation\QuotedMessageData;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Attachment;
use App\Models\ConversationMessage;
use Spatie\LaravelData\Data;

/**
 * 接待消息数据。
 * 由后端组装后传给 resources/js/standalone/StandaloneRoot.vue，用于页面展示、抽屉详情或局部交互状态。
 */
class ReceptionMessageData extends Data
{
    /**
     * 撤回消息时仍保留原 content（用于审计），由前端依据 recalled_at 渲染撤回占位。
     */
    public function __construct(
        public string $id,
        public MessageRole $role,
        public MessageKind $kind,
        public string $content,
        public ?string $sender_name,
        public ?string $sender_avatar_url,
        public string $created_at,
        public int $seq_no,
        public ?string $client_msg_id,
        public MessageDeliveryStatus $delivery_status,
        public ?string $quoted_message_id,
        public ?QuotedMessageData $quoted_message,
        public ?string $recalled_at,
        /**
         * 已撤回消息的原始文本，仅在 viewer 即撤回者本人（访客端：role=visitor）时下发；
         * 用于支持"重新编辑"重新填回输入框。
         */
        public ?string $recalled_content = null,
        /** @var list<ReceptionAttachmentData> */
        public array $attachments = [],
    ) {}

    public static function fromModel(
        ConversationMessage $message,
        ?string $senderName = null,
        ?string $senderAvatarUrl = null,
        ?QuotedMessageData $quotedMessage = null,
    ): self {
        $attachments = $message->relationLoaded('attachments')
            ? $message->attachments
            : collect();

        $isRecalled = $message->isRecalled();
        // 访客端的 viewer 始终是访客本人，仅其自己撤回的消息暴露原文用于重新编辑。
        $recalledContent = $isRecalled && $message->role === MessageRole::Visitor
            ? (string) ($message->content ?? '')
            : null;

        return new self(
            id: (string) $message->id,
            role: $message->role,
            kind: $message->kind,
            content: $isRecalled ? '' : (string) ($message->content ?? ''),
            sender_name: $senderName,
            sender_avatar_url: $senderAvatarUrl,
            created_at: $message->created_at->toIso8601String(),
            seq_no: (int) $message->seq_no,
            client_msg_id: $message->client_msg_id,
            delivery_status: $message->delivery_status,
            quoted_message_id: $message->quoted_message_id,
            quoted_message: $quotedMessage,
            recalled_at: $message->recalled_at?->toIso8601String(),
            recalled_content: $recalledContent,
            attachments: $isRecalled
                ? []
                : $attachments
                    ->map(fn (Attachment $attachment): ReceptionAttachmentData => ReceptionAttachmentData::fromModel($attachment))
                    ->values()
                    ->all(),
        );
    }
}
