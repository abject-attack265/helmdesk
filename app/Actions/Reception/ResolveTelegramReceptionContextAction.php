<?php

namespace App\Actions\Reception;

use App\Actions\Contact\ResolveContactIdentityAction;
use App\Enums\AttachmentPurpose;
use App\Enums\AttachmentStatus;
use App\Enums\ChannelType;
use App\Enums\ContactSource;
use App\Enums\ConversationEntryMode;
use App\Enums\IdentityType;
use App\Models\Attachment;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Services\Storage\AttachmentPathGenerator;
use App\Services\Storage\StorageProfileDisk;
use App\Services\Storage\StorageProfileResolver;
use App\Services\Telegram\TelegramBotApi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * 解析 Telegram 渠道访客接待上下文：按 Telegram 用户解析联系人，并复用共享会话解析逻辑。
 *
 * 访客身份用 ChannelAccount（渠道账号）表达，namespace 固定为 "telegram:{channel_code}"，
 * value 为 Telegram 用户 id；这是渠道技术标识，与业务方传入的 ExternalId 身份分属两类。
 */
class ResolveTelegramReceptionContextAction
{
    use AsAction;

    /**
     * 注入联系人身份解析与共享会话解析服务。
     */
    public function __construct(
        private readonly ResolveContactIdentityAction $resolveContactIdentityAction,
        private readonly FindOrCreateReceptionConversationAction $findOrCreateReceptionConversationAction,
        private readonly TelegramBotApi $telegramApi,
        private readonly AttachmentPathGenerator $pathGenerator,
        private readonly StorageProfileResolver $profileResolver,
    ) {}

    /**
     * 解析 Telegram 访客上下文并返回渠道、联系人与会话。
     *
     * @return array{channel: Channel, contact: Contact, conversation: Conversation, created: bool}
     */
    public function handle(string $channelCode, string $telegramUserId, ?string $displayName = null): array
    {
        $channel = $this->findActiveChannel($channelCode);

        $contact = $this->resolveContactIdentityAction->handle(
            [
                'type' => IdentityType::ChannelAccount,
                'value' => $telegramUserId,
                'namespace' => self::identityNamespace($channelCode),
            ],
            ContactSource::Telegram,
            name: $displayName,
        );

        $this->touchContact($contact, $displayName);
        $this->syncTelegramAvatar($channel, $contact, $telegramUserId);

        $settings = $channel->telegramSettings();

        [$conversation, $created] = $this->findOrCreateReceptionConversationAction->handle(
            $channel,
            $contact,
            ConversationEntryMode::Telegram,
            $settings->default_visitor_locale->value,
        );

        return [
            'channel' => $channel,
            'contact' => $contact->fresh() ?? $contact,
            'conversation' => $conversation,
            'created' => $created,
        ];
    }

    /**
     * 返回 Telegram 渠道账号身份的 namespace（"telegram:{code}"），用于区分同应用的多个 bot 渠道。
     */
    public static function identityNamespace(string $channelCode): string
    {
        return 'telegram:'.$channelCode;
    }

    /**
     * 查找用于接待的 Telegram 渠道。
     *
     * 软删除（暂停）渠道仍返回，由共享会话解析逻辑区分：已有进行中会话可继续，无则拒绝新建。
     */
    private function findActiveChannel(string $channelCode): Channel
    {
        $channel = Channel::query()
            ->withTrashed()
            ->where('code', $channelCode)
            ->where('type', ChannelType::Telegram)
            ->with('receptionPlan')
            ->first();

        if ($channel === null) {
            throw new NotFoundHttpException;
        }

        return $channel;
    }

    /**
     * 更新联系人最近访问时间，并在展示名缺失时补齐 Telegram 提供的名称。
     */
    private function touchContact(Contact $contact, ?string $displayName): void
    {
        $updates = ['last_seen_at' => now()];

        if (filled($displayName) && ! filled($contact->name)) {
            $updates['name'] = $displayName;
        }

        $contact->forceFill($updates)->saveQuietly();
    }

    /**
     * 首次接触 Telegram 访客时尝试把用户头像下载为本地附件并写入联系人头像 URL。
     *
     * 已同步过（avatar_synced_at 非空）或已有自定义头像的联系人直接跳过；
     * 一次完整探测后会打上 avatar_synced_at，即使访客没有头像也不会每条消息重复请求 Bot API。
     * 仅网络/接口异常时不打标，留待下一条消息重试。
     */
    private function syncTelegramAvatar(Channel $channel, Contact $contact, string $telegramUserId): void
    {
        if ($contact->avatar_synced_at !== null || $contact->hasCustomAvatar()) {
            return;
        }

        $botToken = (string) $channel->telegramSettings()->bot_token;
        if ($botToken === '' || ! ctype_digit($telegramUserId)) {
            return;
        }

        try {
            $photos = $this->telegramApi->getUserProfilePhotos($botToken, (int) $telegramUserId, 1);
            $fileId = $this->largestProfilePhotoFileId($photos);

            $updates = ['avatar_synced_at' => now()];

            if ($fileId !== null) {
                $file = $this->telegramApi->getFile($botToken, $fileId);
                $filePath = is_string($file['file_path'] ?? null) ? $file['file_path'] : '';

                if ($filePath !== '') {
                    $contents = $this->telegramApi->downloadFile($botToken, $filePath);
                    $attachment = $this->storeAvatarAttachment($contact, $contents, basename($filePath));
                    $updates['avatar_url'] = $attachment->full_url;
                }
            }

            $contact->forceFill($updates)->saveQuietly();
        } catch (Throwable $e) {
            Log::warning('Telegram 用户头像同步失败。', [
                'channel_id' => (string) $channel->id,
                'contact_id' => (string) $contact->id,
                'telegram_user_id' => $telegramUserId,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 从 getUserProfilePhotos 结果中取第一张头像的最大尺寸 file_id。
     *
     * @param  array<string, mixed>  $photos
     */
    private function largestProfilePhotoFileId(array $photos): ?string
    {
        $sets = $photos['photos'] ?? null;
        if (! is_array($sets) || ! is_array($sets[0] ?? null)) {
            return null;
        }

        $largest = array_last($sets[0]);
        if (! is_array($largest)) {
            return null;
        }

        $fileId = $largest['file_id'] ?? null;

        return is_string($fileId) && $fileId !== '' ? $fileId : null;
    }

    /**
     * 将 Telegram 下载到的头像内容写入附件存储并绑定到联系人。
     */
    private function storeAvatarAttachment(Contact $contact, string $contents, string $fileName): Attachment
    {
        $profile = $this->profileResolver->resolveForNewUpload();
        $imageSize = @getimagesizefromstring($contents);
        $mimeType = $this->resolveImageMimeType($imageSize);
        $metadata = is_array($imageSize)
            ? ['width' => (int) $imageSize[0], 'height' => (int) $imageSize[1]]
            : [];

        $attachmentId = (string) Str::ulid();
        $objectKey = $this->pathGenerator->generate(
            attachmentId: $attachmentId,
            purpose: AttachmentPurpose::Avatar,
            originalName: $fileName,
            mimeType: $mimeType,
        );

        $disk = StorageProfileDisk::build($profile);
        $originalName = $fileName !== '' ? $fileName : 'telegram-avatar.jpg';
        if (! $disk->put($objectKey, $contents, [
            'ContentType' => $mimeType,
            'ContentDisposition' => Attachment::dispositionFor($mimeType, $originalName),
            'CacheControl' => Attachment::CACHE_CONTROL,
        ])) {
            throw new \RuntimeException('Telegram 用户头像落盘失败');
        }

        return Attachment::query()->create([
            'id' => $attachmentId,
            'uploaded_by_user_id' => null,
            'storage_profile_id' => $profile->id,
            'object_key' => $objectKey,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'extension' => $this->pathGenerator->extension($fileName, $mimeType),
            'byte_size' => strlen($contents),
            'checksum_sha256' => hash('sha256', $contents),
            'purpose' => AttachmentPurpose::Avatar,
            'status' => AttachmentStatus::Attached,
            'attachable_type' => $contact->getMorphClass(),
            'attachable_id' => $contact->getKey(),
            'metadata' => $metadata,
            'uploaded_at' => now(),
            'attached_at' => now(),
        ]);
    }

    /**
     * 从 getimagesizefromstring 结果识别头像 MIME，无法识别时按 Telegram 常见 JPG 头像处理。
     *
     * @param  array<int|string, mixed>|false  $imageSize
     */
    private function resolveImageMimeType(array|false $imageSize): string
    {
        $mimeType = is_array($imageSize) && is_string($imageSize['mime'] ?? null)
            ? $imageSize['mime']
            : 'image/jpeg';

        return str_starts_with($mimeType, 'image/') ? $mimeType : 'image/jpeg';
    }
}
