<?php

namespace App\Actions\Reception;

use App\Actions\Attachment\ValidateAttachmentUploadAction;
use App\Enums\AttachmentPurpose;
use App\Enums\AttachmentStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use App\Services\Storage\AttachmentMimeDetector;
use App\Services\Storage\AttachmentPathGenerator;
use App\Services\Storage\StorageProfileDisk;
use App\Services\Storage\StorageProfileResolver;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/** 保存 Telegram 入站媒体及可选 caption，并更新接待会话。 */
class AppendTelegramVisitorMediaAction
{
    use AsAction;

    /** Telegram 图片 / 文件 caption 上限 1024 字符。 */
    private const int MAX_CAPTION_LENGTH = 1024;

    /** 创建 Telegram 媒体写入流程。 */
    public function __construct(
        private readonly ResolveTelegramReceptionContextAction $resolveTelegramReceptionContextAction,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
        private readonly AttachmentPathGenerator $pathGenerator,
        private readonly StorageProfileResolver $profileResolver,
        private readonly ValidateAttachmentUploadAction $uploadValidator,
        private readonly AttachmentMimeDetector $mimeDetector,
    ) {}

    /**
     * 解析上下文并追加一条 Telegram 媒体消息（可选附带 caption 文本消息）。
     *
     * @param  'image'|'file'  $mediaKind
     * @return array{conversation: Conversation, message: ?ConversationMessage}
     */
    public function handle(
        string $channelCode,
        string $telegramUserId,
        ?string $displayName,
        string $mediaKind,
        string $fileContents,
        string $fileName,
        string $mimeType,
        ?string $caption,
        int $telegramMessageId,
        int $telegramChatId,
    ): array {
        $context = $this->resolveTelegramReceptionContextAction->handle($channelCode, $telegramUserId, $displayName);
        /** @var Conversation $conversation */
        $conversation = $context['conversation'];
        $conversation->loadMissing('contact');
        $visitorSenderName = (string) ($conversation->contact?->name ?? $displayName ?? 'Telegram');

        $caption = $caption !== null ? Str::limit(trim($caption), self::MAX_CAPTION_LENGTH, '') : '';
        $clientMsgId = 'tg_'.$telegramMessageId;
        $captionClientMsgId = $clientMsgId.'_caption';
        $existing = ConversationMessage::findByClientMsgId($conversation->id, $clientMsgId);
        if ($existing !== null) {
            $captionMessage = $caption === ''
                ? null
                : ConversationMessage::findByClientMsgId($conversation->id, $captionClientMsgId);

            return ['conversation' => $conversation->refresh(), 'message' => $captionMessage];
        }

        $kind = $mediaKind === 'image' ? MessageKind::Image : MessageKind::File;
        $purpose = $mediaKind === 'image' ? AttachmentPurpose::ConversationImage : AttachmentPurpose::ConversationFile;
        $realMimeType = $this->mimeDetector->detectContents($fileContents);
        if ($realMimeType === null) {
            throw ValidationException::withMessages([
                'mime_type' => __('attachments.errors.object_mime_mismatch'),
            ]);
        }
        $this->uploadValidator->handle($purpose, $realMimeType, strlen($fileContents));

        $telegramPayload = ['message_id' => $telegramMessageId, 'chat_id' => $telegramChatId];
        $profile = $this->profileResolver->resolveForNewUpload();
        $attachmentId = (string) Str::ulid();
        $objectKey = $this->pathGenerator->generate(
            attachmentId: $attachmentId,
            purpose: $purpose,
            originalName: $fileName,
            mimeType: $realMimeType,
        );
        $disk = StorageProfileDisk::build($profile);
        if (! $disk->put($objectKey, $fileContents, [
            'ContentType' => $realMimeType,
            'ContentDisposition' => Attachment::dispositionFor($realMimeType, $fileName),
            'CacheControl' => Attachment::CACHE_CONTROL,
        ])) {
            throw new \RuntimeException('Telegram 媒体附件落盘失败。');
        }

        try {
            [$mediaMessage, $captionMessage] = DB::transaction(function () use (
                $conversation, $visitorSenderName, $clientMsgId, $kind, $purpose,
                $fileContents, $fileName, $realMimeType, $caption, $telegramPayload,
                $profile, $attachmentId, $objectKey, $captionClientMsgId,
            ): array {
                $mediaMessage = ConversationMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'role' => MessageRole::Visitor,
                    'sender_name' => $visitorSenderName,
                    'kind' => $kind,
                    'content' => null,
                    'payload' => ['telegram' => $telegramPayload],
                    'client_msg_id' => $clientMsgId,
                ]);

                $attachment = Attachment::query()->create([
                    'id' => $attachmentId,
                    'uploaded_by_user_id' => null,
                    'storage_profile_id' => $profile->id,
                    'object_key' => $objectKey,
                    'original_name' => $fileName,
                    'mime_type' => $realMimeType,
                    'extension' => $this->pathGenerator->extension($fileName, $realMimeType),
                    'byte_size' => strlen($fileContents),
                    'checksum_sha256' => hash('sha256', $fileContents),
                    'purpose' => $purpose,
                    'status' => AttachmentStatus::Attached,
                    'attachable_type' => $mediaMessage->getMorphClass(),
                    'attachable_id' => $mediaMessage->getKey(),
                    'uploaded_at' => now(),
                    'attached_at' => now(),
                ]);

                $mediaMessage->update([
                    'payload' => [
                        'telegram' => $telegramPayload,
                        'attachments' => [ConversationMessage::attachmentSnapshot($attachment)],
                    ],
                ]);

                $captionMessage = null;
                if ($caption !== '') {
                    $captionMessage = ConversationMessage::query()->create([
                        'conversation_id' => $conversation->id,
                        'role' => MessageRole::Visitor,
                        'sender_name' => $visitorSenderName,
                        'kind' => MessageKind::Text,
                        'content' => $caption,
                        'payload' => ['telegram' => $telegramPayload],
                        'client_msg_id' => $captionClientMsgId,
                    ]);
                }

                $preview = $caption !== '' ? $caption : $mediaMessage->attachmentPreview();
                $conversation->update([
                    'last_message_at' => now(),
                    'last_message_preview' => Conversation::messagePreview($preview),
                    'waiting_for_visitor_reply' => false,
                    'unread_agent_message_count' => 0,
                ]);
                Conversation::query()
                    ->whereKey($conversation->id)
                    ->increment('unread_visitor_message_count');

                return [$mediaMessage, $captionMessage];
            });
        } catch (Throwable $exception) {
            $this->deleteStoredObject($disk, $objectKey, $exception);

            if ($exception instanceof UniqueConstraintViolationException) {
                $existing = ConversationMessage::findByClientMsgId($conversation->id, $clientMsgId);
                if ($existing !== null) {
                    Log::info('Telegram 并发重复媒体消息已跳过写入。', [
                        'conversation_id' => (string) $conversation->id,
                        'telegram_message_id' => $telegramMessageId,
                    ]);

                    $captionMessage = $caption === ''
                        ? null
                        : ConversationMessage::findByClientMsgId($conversation->id, $captionClientMsgId);

                    return ['conversation' => $conversation->refresh(), 'message' => $captionMessage];
                }
            }

            throw $exception;
        }

        $conversation->refresh();

        $this->realtimeNotifier->conversationChanged(
            $conversation,
            'visitor_message_created',
            meta: $mediaMessage->realtimeMeta(),
        );

        return ['conversation' => $conversation, 'message' => $captionMessage];
    }

    /** 删除事务失败后遗留的媒体对象，并记录清理异常。 */
    private function deleteStoredObject(Filesystem $disk, string $objectKey, Throwable $reason): void
    {
        try {
            if ($disk->delete($objectKey)) {
                return;
            }

            Log::warning('Telegram 入站媒体对象清理失败。', [
                'object_key' => $objectKey,
                'reason' => $reason->getMessage(),
            ]);
        } catch (Throwable $cleanupException) {
            Log::warning('Telegram 入站媒体对象清理异常。', [
                'object_key' => $objectKey,
                'reason' => $reason->getMessage(),
                'cleanup_exception' => $cleanupException::class,
                'cleanup_reason' => $cleanupException->getMessage(),
            ]);
        }
    }
}
