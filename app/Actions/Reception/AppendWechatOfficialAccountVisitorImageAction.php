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
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/** 保存微信公众号访客图片并更新接待会话。 */
class AppendWechatOfficialAccountVisitorImageAction
{
    use AsAction;

    /** 创建微信公众号访客图片写入流程。 */
    public function __construct(
        private readonly ResolveWechatOfficialAccountReceptionContextAction $resolveContext,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
        private readonly CaptureWechatOfficialAccountConversationContextAction $captureContext,
        private readonly StorageProfileResolver $profileResolver,
        private readonly AttachmentPathGenerator $pathGenerator,
        private readonly ValidateAttachmentUploadAction $uploadValidator,
        private readonly AttachmentMimeDetector $mimeDetector,
    ) {}

    /**
     * 幂等保存微信图片并返回对应会话消息。
     *
     * @return array{conversation: Conversation, message: ConversationMessage}
     */
    public function handle(
        string $channelCode,
        string $openid,
        string $wechatMessageId,
        string $contents,
        string $fileName,
        string $mimeType,
        ?string $displayName = null,
        ?string $language = null,
    ): array {
        $openid = trim($openid);
        $wechatMessageId = trim($wechatMessageId);
        if ($openid === '' || $wechatMessageId === '') {
            throw ValidationException::withMessages(['message_id' => '微信公众号图片缺少访客或消息标识。']);
        }

        $context = $this->resolveContext->handle($channelCode, $openid, $displayName);
        /** @var Conversation $conversation */
        $conversation = $context['conversation'];
        $conversation->loadMissing('contact');
        $this->captureContext->handle($conversation, $openid, $displayName, $language);

        $clientMsgId = 'wxoa_'.$channelCode.'_'.$wechatMessageId;
        $existing = ConversationMessage::findByClientMsgId($conversation->id, $clientMsgId);
        if ($existing !== null) {
            return ['conversation' => $conversation->refresh(), 'message' => $existing];
        }

        $realMimeType = $this->mimeDetector->detectContents($contents);
        if ($realMimeType === null) {
            throw ValidationException::withMessages([
                'mime_type' => __('attachments.errors.object_mime_mismatch'),
            ]);
        }
        $this->uploadValidator->handle(
            AttachmentPurpose::ConversationImage,
            $realMimeType,
            strlen($contents),
        );

        $profile = $this->profileResolver->resolveForNewUpload();
        $attachmentId = (string) Str::ulid();
        $objectKey = $this->pathGenerator->generate(
            attachmentId: $attachmentId,
            purpose: AttachmentPurpose::ConversationImage,
            originalName: $fileName,
            mimeType: $realMimeType,
        );
        $disk = StorageProfileDisk::build($profile);
        if (! $disk->put($objectKey, $contents, [
            'ContentType' => $realMimeType,
            'ContentDisposition' => Attachment::dispositionFor($realMimeType, $fileName),
            'CacheControl' => Attachment::CACHE_CONTROL,
        ])) {
            throw new \RuntimeException('微信公众号图片落盘失败。');
        }

        try {
            $message = DB::transaction(function () use (
                $conversation, $clientMsgId, $wechatMessageId, $displayName, $profile,
                $attachmentId, $objectKey, $fileName, $realMimeType, $contents
            ): ConversationMessage {
                $message = ConversationMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'role' => MessageRole::Visitor,
                    'sender_name' => (string) ($conversation->contact?->name ?? $displayName ?? '微信公众号访客'),
                    'kind' => MessageKind::Image,
                    'content' => null,
                    'payload' => ['wechat_oa' => ['message_id' => $wechatMessageId]],
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
                    'byte_size' => strlen($contents),
                    'checksum_sha256' => hash('sha256', $contents),
                    'purpose' => AttachmentPurpose::ConversationImage,
                    'status' => AttachmentStatus::Attached,
                    'attachable_type' => $message->getMorphClass(),
                    'attachable_id' => $message->getKey(),
                    'uploaded_at' => now(),
                    'attached_at' => now(),
                ]);

                $message->update([
                    'payload' => [
                        'wechat_oa' => ['message_id' => $wechatMessageId],
                        'attachments' => [ConversationMessage::attachmentSnapshot($attachment)],
                    ],
                ]);
                $conversation->update([
                    'last_message_at' => now(),
                    'last_message_preview' => Conversation::messagePreview($message->attachmentPreview()),
                    'waiting_for_visitor_reply' => false,
                    'unread_agent_message_count' => 0,
                ]);
                Conversation::query()->whereKey($conversation->id)->increment('unread_visitor_message_count');

                return $message;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredObject($disk, $objectKey, $exception);

            if ($exception instanceof UniqueConstraintViolationException) {
                $existing = ConversationMessage::findByClientMsgId($conversation->id, $clientMsgId);
                if ($existing !== null) {
                    Log::info('微信公众号并发重复图片已跳过写入。', [
                        'conversation_id' => (string) $conversation->id,
                        'wechat_message_id' => $wechatMessageId,
                    ]);

                    return ['conversation' => $conversation->refresh(), 'message' => $existing];
                }
            }

            throw $exception;
        }

        $conversation->refresh();
        $this->realtimeNotifier->conversationChanged(
            $conversation,
            'visitor_message_created',
            meta: $message->realtimeMeta(),
        );

        return ['conversation' => $conversation, 'message' => $message];
    }

    /** 删除事务失败后遗留的微信图片对象，并记录清理异常。 */
    private function deleteStoredObject(Filesystem $disk, string $objectKey, Throwable $reason): void
    {
        try {
            if ($disk->delete($objectKey)) {
                return;
            }

            Log::warning('微信公众号入站图片对象清理失败。', [
                'object_key' => $objectKey,
                'reason' => $reason->getMessage(),
            ]);
        } catch (Throwable $cleanupException) {
            Log::warning('微信公众号入站图片对象清理异常。', [
                'object_key' => $objectKey,
                'reason' => $reason->getMessage(),
                'cleanup_exception' => $cleanupException::class,
                'cleanup_reason' => $cleanupException->getMessage(),
            ]);
        }
    }
}
