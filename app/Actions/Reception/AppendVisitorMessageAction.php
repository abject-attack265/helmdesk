<?php

namespace App\Actions\Reception;

use App\Actions\Attachment\AttachUploadedAttachmentsAction;
use App\Data\Reception\ReceptionStateData;
use App\Enums\AttachmentPurpose;
use App\Enums\ConversationEntryMode;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Attachment;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use App\Services\Reception\ReceptionStateBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 向接待会话追加访客消息、绑定附件并刷新接待状态。
 */
class AppendVisitorMessageAction
{
    use AsAction;

    public const int MAX_CONTENT_LENGTH = 4000;

    public const int MAX_ATTACHMENT_COUNT = 10;

    /**
     * 注入接待上下文、实时通知和附件绑定服务。
     */
    public function __construct(
        private readonly ResolveReceptionContextAction $resolveReceptionContextAction,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
        private readonly AttachUploadedAttachmentsAction $attachUploadedAttachmentsAction,
    ) {}

    /**
     * 分别保存文本与附件消息，并按客户端消息 ID 保证发送幂等。
     *
     * @param  list<string>  $attachmentIds
     * @param  array<string, string>|null  $queryParams
     * @param  array<string, mixed>|null  $visitorClient
     */
    public function handle(
        string $channelCode,
        ?string $sessionToken,
        string $content,
        ?ConversationEntryMode $entryMode = null,
        ?array $visitorEnvironment = null,
        array $attachmentIds = [],
        ?string $userToken = null,
        ?array $queryParams = null,
        ?array $visitorClient = null,
        ?string $clientMsgId = null,
        ?string $quotedMessageId = null,
    ): ReceptionStateData {
        $content = trim($content);
        if ($content === '' && $attachmentIds === []) {
            throw ValidationException::withMessages(['content' => __('conversation.errors.empty_message')]);
        }
        if (Str::length($content) > self::MAX_CONTENT_LENGTH) {
            throw ValidationException::withMessages(['content' => __('conversation.errors.message_too_long')]);
        }
        if (count($attachmentIds) > self::MAX_ATTACHMENT_COUNT) {
            throw ValidationException::withMessages(['attachment_ids' => __('validation.max.array', ['max' => self::MAX_ATTACHMENT_COUNT])]);
        }

        /** @var array{channel: Channel, contact: Contact, conversation: Conversation, session_token: string}|null $context */
        $context = null;

        try {
            /** @var array{channel: Channel, contact: Contact, conversation: Conversation, session_token: string, created_message_ids: list<string>} $result */
            $result = DB::transaction(function () use (
                $channelCode,
                $sessionToken,
                $entryMode,
                $visitorEnvironment,
                $userToken,
                $queryParams,
                $visitorClient,
                $content,
                $attachmentIds,
                $clientMsgId,
                $quotedMessageId,
                &$context,
            ): array {
                $context = $this->resolveReceptionContextAction->handle(
                    $channelCode,
                    $sessionToken,
                    $entryMode,
                    $visitorEnvironment,
                    $userToken,
                    $queryParams,
                    $visitorClient,
                );
                $conversation = $context['conversation'];
                $contact = $context['contact'];
                if ($clientMsgId !== null && ConversationMessage::findByClientMsgId($conversation->id, $clientMsgId) !== null) {
                    return [
                        'channel' => $context['channel'],
                        'contact' => $contact,
                        'conversation' => $conversation,
                        'session_token' => $context['session_token'],
                        'created_message_ids' => [],
                    ];
                }

                $visitorSenderName = (string) $contact->name;
                $resolvedQuotedMessageId = ConversationMessage::resolveQuotedMessageId($conversation->id, $quotedMessageId);
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
                        'conversation_id' => $conversation->id,
                        'role' => MessageRole::Visitor,
                        'sender_name' => $visitorSenderName,
                        'kind' => MessageKind::Text,
                        'content' => $content,
                        'content_locale' => null,
                        'payload' => null,
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
                        'conversation_id' => $conversation->id,
                        'role' => MessageRole::Visitor,
                        'sender_name' => $visitorSenderName,
                        'kind' => $kind,
                        'content' => null,
                        'payload' => null,
                        // 批次首条消息保存 client_msg_id，作为整次发送的幂等键。
                        'client_msg_id' => $firstClientMsgIdConsumed ? null : $clientMsgId,
                        'quoted_message_id' => $firstClientMsgIdConsumed ? null : $resolvedQuotedMessageId,
                    ]);
                    $firstClientMsgIdConsumed = $firstClientMsgIdConsumed || $clientMsgId !== null;

                    $attached = $this->attachUploadedAttachmentsAction->handle(
                        attachable: $attachmentMessage,
                        attachmentId: (string) $attachment->id,
                        sessionToken: $context['session_token'],
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
                $previewSource = $content !== '' ? $content : $lastMessage->attachmentPreview();

                $conversation->update([
                    'last_message_at' => now(),
                    'last_message_preview' => Conversation::messagePreview($previewSource),
                    'waiting_for_visitor_reply' => false,
                    // 访客发送消息时确认已读客服与 AI 回复。
                    'unread_agent_message_count' => 0,
                ]);

                Conversation::query()
                    ->whereKey($conversation->id)
                    ->increment('unread_visitor_message_count', $messages->count());

                return [
                    'channel' => $context['channel'],
                    'contact' => $contact,
                    'conversation' => $conversation,
                    'session_token' => $context['session_token'],
                    'created_message_ids' => $messages
                        ->map(static fn (ConversationMessage $message): string => (string) $message->id)
                        ->all(),
                ];
            });
        } catch (UniqueConstraintViolationException $exception) {
            $conversation = $context['conversation'] ?? null;
            $existingMessage = $conversation !== null && $clientMsgId !== null
                ? ConversationMessage::findByClientMsgId($conversation->id, $clientMsgId)
                : null;

            if ($conversation !== null && $existingMessage !== null) {
                Log::info('[reception] 访客并发重复消息已跳过写入', [
                    'conversation_id' => (string) $conversation->id,
                    'client_msg_id' => $clientMsgId,
                ]);

                $conversation->refresh();

                return ReceptionStateBuilder::build($context['channel'], $conversation, $context['session_token']);
            }

            throw $exception;
        }

        $conversation = $result['conversation'];
        $contact = $result['contact'];
        $createdMessageIds = $result['created_message_ids'];

        if ($createdMessageIds === []) {
            Log::info('[reception] 访客重复消息已跳过写入', [
                'conversation_id' => (string) $conversation->id,
                'client_msg_id' => $clientMsgId,
            ]);

            $conversation->refresh();

            return ReceptionStateBuilder::build($result['channel'], $conversation, $result['session_token']);
        }

        $conversation->refresh();
        $latestMessageId = array_last($createdMessageIds);
        $latestMessage = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->findOrFail($latestMessageId);

        Log::info('[reception] 访客消息已落库', [
            'conversation_id' => (string) $conversation->id,
            'channel_id' => (string) $result['channel']->id,
            'contact_id' => (string) $contact->id,
            'message_id' => $latestMessageId,
            'message_count' => count($createdMessageIds),
        ]);

        // 广播延后到响应发送后，避免 Mercure I/O 阻塞访客消息请求。
        $broadcastMeta = $latestMessage->realtimeMeta();
        defer(fn () => $this->realtimeNotifier->conversationChanged(
            $conversation,
            'visitor_message_created',
            meta: $broadcastMeta,
        ));

        return ReceptionStateBuilder::build($result['channel'], $conversation, $result['session_token']);
    }
}
