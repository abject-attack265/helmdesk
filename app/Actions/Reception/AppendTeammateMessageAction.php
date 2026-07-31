<?php

namespace App\Actions\Reception;

use App\Actions\Attachment\AttachUploadedAttachmentsAction;
use App\Enums\AttachmentPurpose;
use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Exceptions\BusinessException;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\Conversation\ConversationReplyRule;
use App\Services\Conversation\TeammateMessageLimits;
use App\Services\LocalePreference;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 向接待会话追加客服消息。
 */
class AppendTeammateMessageAction
{
    use AsAction;

    /**
     * 注入实时通知和附件绑定服务。
     */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
        private readonly AttachUploadedAttachmentsAction $attachUploadedAttachmentsAction,
        private readonly ConversationReplyRule $replyRule,
    ) {}

    /**
     * 追加客服文本与附件消息，并按客户端消息 ID 保证发送幂等。
     */
    public function handle(
        Conversation $conversation,
        User $actor,
        string $content,
        array $attachmentIds = [],
        ?string $clientMsgId = null,
        ?string $quotedMessageId = null,
        ?string $contentLocale = null,
        ?string $authorContent = null,
        ?string $authorLocale = null,
    ): ConversationMessage {
        $content = trim($content);
        if ($content === '' && $attachmentIds === []) {
            throw ValidationException::withMessages(['content' => __('conversation.errors.empty_message')]);
        }
        if (Str::length($content) > TeammateMessageLimits::MAX_CONTENT_LENGTH) {
            throw ValidationException::withMessages(['content' => __('conversation.errors.message_too_long')]);
        }
        if (count($attachmentIds) > TeammateMessageLimits::MAX_ATTACHMENT_COUNT) {
            throw ValidationException::withMessages(['attachment_ids' => __('validation.max.array', ['max' => TeammateMessageLimits::MAX_ATTACHMENT_COUNT])]);
        }

        if ($clientMsgId !== null) {
            $existing = ConversationMessage::findByClientMsgId($conversation->id, $clientMsgId);
            if ($existing !== null) {
                return $existing;
            }
        }

        $resolvedQuotedMessageId = ConversationMessage::resolveQuotedMessageId($conversation->id, $quotedMessageId);
        $authorTranslationPayload = $this->authorTranslationPayload(
            authorContent: $authorContent,
            authorLocale: $authorLocale,
            contentLocale: $contentLocale,
        );

        try {
            $message = DB::transaction(function () use ($conversation, $actor, $content, $contentLocale, $attachmentIds, $clientMsgId, $resolvedQuotedMessageId, $authorTranslationPayload) {
                $lockedConversation = Conversation::query()
                    ->whereKey($conversation->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $denialMessageKey = $this->replyRule->denialMessageKey($lockedConversation, $actor);

                if ($denialMessageKey !== null) {
                    throw new BusinessException(__($denialMessageKey));
                }

                $resolvedAttachments = collect();

                if ($attachmentIds !== []) {
                    $attachmentsById = Attachment::query()
                        ->whereIn('id', $attachmentIds)
                        ->get()
                        ->keyBy(fn (Attachment $attachment): string => (string) $attachment->id);

                    $resolvedAttachments = collect($attachmentIds)
                        ->map(fn (string $attachmentId): ?Attachment => $attachmentsById->get($attachmentId))
                        ->filter();

                    if ($resolvedAttachments->count() !== count($attachmentIds)) {
                        throw ValidationException::withMessages(['attachment_ids' => __('attachments.errors.not_uploaded')]);
                    }
                }

                $messages = collect();
                $firstClientMsgIdConsumed = false;

                if ($content !== '') {
                    $messages->push(ConversationMessage::query()->create([
                        'conversation_id' => $lockedConversation->id,
                        'sender_user_id' => $actor->id,
                        'sender_name' => $actor->name,
                        'role' => MessageRole::Teammate,
                        'kind' => MessageKind::Text,
                        'content' => $content,
                        'content_locale' => $contentLocale,
                        'payload' => $authorTranslationPayload,
                        'client_msg_id' => $clientMsgId,
                        'quoted_message_id' => $resolvedQuotedMessageId,
                    ]));
                    $firstClientMsgIdConsumed = $clientMsgId !== null;
                }

                foreach ($resolvedAttachments as $attachment) {
                    $kind = $attachment->purpose === AttachmentPurpose::ConversationImage
                        ? MessageKind::Image
                        : MessageKind::File;

                    $attachmentMessage = ConversationMessage::query()->create([
                        'conversation_id' => $lockedConversation->id,
                        'sender_user_id' => $actor->id,
                        'sender_name' => $actor->name,
                        'role' => MessageRole::Teammate,
                        'kind' => $kind,
                        'content' => null,
                        'payload' => null,
                        'client_msg_id' => $firstClientMsgIdConsumed ? null : $clientMsgId,
                        'quoted_message_id' => $firstClientMsgIdConsumed ? null : $resolvedQuotedMessageId,
                    ]);
                    $firstClientMsgIdConsumed = $firstClientMsgIdConsumed || $clientMsgId !== null;

                    $attached = $this->attachUploadedAttachmentsAction->handle(
                        attachable: $attachmentMessage,
                        attachmentId: (string) $attachment->id,
                        actor: $actor,
                        allowedPurposes: [AttachmentPurpose::ConversationImage, AttachmentPurpose::ConversationFile],
                    );

                    $attachmentMessage->update([
                        'payload' => [
                            'attachments' => [ConversationMessage::attachmentSnapshot($attached)],
                        ],
                    ]);

                    $messages->push($attachmentMessage);
                }

                /** @var ConversationMessage $lastMessage */
                $lastMessage = $messages->last();

                $update = [
                    'last_message_at' => now(),
                    'last_message_preview' => Conversation::messagePreview($content !== '' ? $content : $lastMessage->attachmentPreview()),
                    'inbox_status' => ConversationInboxStatus::TeammateHandling,
                    'waiting_for_visitor_reply' => true,
                    'unread_visitor_message_count' => 0,
                ];

                $needsImplicitClaim = $lockedConversation->assigned_user_id === null
                    || $lockedConversation->inbox_status === ConversationInboxStatus::TeammatePending;

                if ($needsImplicitClaim) {
                    $update['assigned_user_id'] = $actor->id;
                }

                $lockedConversation->update($update);

                if ($needsImplicitClaim) {
                    ConversationEvent::query()->create([
                        'conversation_id' => $lockedConversation->id,
                        'actor_user_id' => $actor->id,
                        'type' => ConversationEventType::AssignmentChanged,
                        'payload' => ['source' => 'reply', 'user_id' => (string) $actor->id],
                        'created_at' => now(),
                    ]);
                }

                Conversation::query()
                    ->whereKey($lockedConversation->id)
                    ->increment('unread_agent_message_count', $messages->count());

                /** @var ConversationMessage $firstMessage */
                $firstMessage = $messages->firstOrFail();

                return $firstMessage;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($clientMsgId !== null) {
                $existing = ConversationMessage::findByClientMsgId($conversation->id, $clientMsgId);
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $exception;
        }

        $conversation->refresh();
        $message = $message->refresh();

        $this->realtimeNotifier->conversationChanged(
            $conversation,
            'teammate_message_created',
            meta: $message->realtimeMeta(),
        );

        return $message;
    }

    /**
     * 按识别出的源语言保存客服原始输入，供对应语言的客服查看。
     *
     * @return array{translations: array<string, array{text: string, source_lang: string, target_lang: string, provider_slug: string, latency_ms: int}>}|null
     */
    private function authorTranslationPayload(
        ?string $authorContent,
        ?string $authorLocale,
        ?string $contentLocale,
    ): ?array {
        $text = $authorContent !== null ? trim($authorContent) : '';
        $sourceLocale = $authorLocale !== null ? trim($authorLocale) : '';

        if ($text === '' || $sourceLocale === '') {
            return null;
        }

        if ($contentLocale !== null && LocalePreference::matches($contentLocale, $sourceLocale)) {
            return null;
        }

        return [
            'translations' => [
                $sourceLocale => [
                    'text' => $text,
                    'source_lang' => $contentLocale ?? $sourceLocale,
                    'target_lang' => $sourceLocale,
                    'provider_slug' => 'author',
                    'latency_ms' => 0,
                ],
            ],
        ];
    }
}
