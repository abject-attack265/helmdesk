<?php

namespace App\Actions\Attachment;

use App\Data\Attachment\AttachmentUploadTicketData;
use App\Data\Attachment\FormPresignAttachmentUploadData;
use App\Enums\AttachmentPurpose;
use App\Enums\AttachmentStatus;
use App\Enums\ChannelType;
use App\Models\Attachment;
use App\Models\Channel;
use App\Services\Reception\ReceptionSession;
use App\Services\Storage\AttachmentAccessContext;
use App\Services\Storage\AttachmentPathGenerator;
use App\Services\Storage\MimeType;
use App\Services\Storage\StorageProfileDisk;
use App\Services\Storage\StorageProfileResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 申请附件直传：校验声明信息、建占位附件，签发 presigned PUT 让浏览器直传对象存储（字节不过应用）。
 */
class PresignAttachmentUploadAction
{
    use AsAction;

    /** 单个访客会话 token 允许保留的未绑定上传数量。 */
    private const int MAX_ACTIVE_VISITOR_UPLOADS = 20;

    /**
     * 注入上传规则校验和对象路径生成服务。
     */
    public function __construct(
        private readonly ValidateAttachmentUploadAction $uploadValidator,
        private readonly AttachmentPathGenerator $pathGenerator,
        private readonly StorageProfileResolver $profileResolver,
    ) {}

    /**
     * 校验声明信息、建 pending 附件并返回直传签名票据。
     *
     * @throws ValidationException
     */
    public function handle(
        FormPresignAttachmentUploadData $data,
        AttachmentAccessContext $accessContext,
        bool $preferVisitorSession = false,
    ): AttachmentUploadTicketData {
        $declaredMime = MimeType::normalize($data->mime_type);
        $this->uploadValidator->handle($data->purpose, $declaredMime, $data->byte_size);
        [$userId, $sessionToken] = $this->resolveUploadActor($accessContext, $data->context, $data->purpose, $preferVisitorSession);
        $profile = $this->profileResolver->resolveForNewUpload();
        $disk = StorageProfileDisk::buildForUpload($profile);

        $attachmentId = (string) Str::ulid();
        $objectKey = $this->pathGenerator->generatePending($attachmentId);
        $expiresAt = now()->addMinutes(15);

        Attachment::query()->create([
            'id' => $attachmentId,
            'uploaded_by_user_id' => $userId,
            'storage_profile_id' => $profile->id,
            'object_key' => $objectKey,
            'upload_object_key' => $objectKey,
            'upload_expires_at' => $expiresAt,
            'original_name' => $data->file_name,
            'mime_type' => $declaredMime,
            'extension' => $this->pathGenerator->extension($data->file_name, $declaredMime),
            'byte_size' => $data->byte_size,
            'purpose' => $data->purpose,
            'status' => AttachmentStatus::Pending,
            'session_token_hash' => $sessionToken ? hash('sha256', $sessionToken) : null,
            'metadata' => [],
            'expires_at' => $expiresAt,
        ]);

        $ticket = $disk->temporaryUploadUrl($objectKey, $expiresAt);

        return new AttachmentUploadTicketData(
            attachment_id: $attachmentId,
            upload_url: $ticket['url'],
            upload_headers: $this->sanitizeUploadHeaders($ticket['headers'] ?? []),
            expires_at: $expiresAt->toIso8601String(),
        );
    }

    /**
     * 只返回浏览器可以主动设置的直传请求头。
     * 存储驱动可能把 Host 等浏览器托管的请求头放进签名票据，浏览器会拒绝脚本设置这些请求头。
     *
     * @param  array<string, mixed>  $headers
     * @return array<string, string>
     */
    private function sanitizeUploadHeaders(array $headers): array
    {
        $browserManagedHeaders = [
            'accept-charset',
            'accept-encoding',
            'access-control-request-headers',
            'access-control-request-method',
            'connection',
            'content-length',
            'cookie',
            'cookie2',
            'date',
            'dnt',
            'expect',
            'host',
            'keep-alive',
            'origin',
            'referer',
            'te',
            'trailer',
            'transfer-encoding',
            'upgrade',
            'via',
        ];

        $sanitized = [];
        foreach ($headers as $name => $value) {
            $normalizedName = strtolower((string) $name);
            if (
                in_array($normalizedName, $browserManagedHeaders, true)
                || str_starts_with($normalizedName, 'proxy-')
                || str_starts_with($normalizedName, 'sec-')
                || ! is_scalar($value)
            ) {
                continue;
            }

            $sanitized[(string) $name] = (string) $value;
        }

        return $sanitized;
    }

    /**
     * 接收直传签名申请并返回票据 JSON。
     */
    public function asController(Request $request): JsonResponse
    {
        $data = FormPresignAttachmentUploadData::from($request);

        return response()->json(
            $this->handle(
                data: $data,
                accessContext: AttachmentAccessContext::fromRequest($request),
                preferVisitorSession: $request->route()?->named('visitor.attachments.*') ?? false,
            )->toArray()
        );
    }

    /**
     * 根据登录用户或访客会话解析上传归属。
     *
     * @param  array<string, mixed>  $context
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveUploadActor(AttachmentAccessContext $accessContext, array $context, AttachmentPurpose $purpose, bool $preferVisitorSession): array
    {
        if ($preferVisitorSession) {
            return $this->resolveVisitorUploadActor($accessContext, $context, $purpose);
        }

        $user = array_first($accessContext->users);
        if ($user !== null) {
            return [(string) $user->id, null];
        }

        return $this->resolveVisitorUploadActor($accessContext, $context, $purpose);
    }

    /**
     * 根据访客渠道和会话 token 解析上传归属。
     *
     * @param  array<string, mixed>  $context
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveVisitorUploadActor(AttachmentAccessContext $accessContext, array $context, AttachmentPurpose $purpose): array
    {
        $channelCode = is_string($context['channel_code'] ?? null) ? $context['channel_code'] : null;
        if (! $channelCode) {
            throw ValidationException::withMessages(['context.channel_code' => __('validation.required', ['attribute' => 'channel_code'])]);
        }

        $token = ReceptionSession::normalize($accessContext->visitorTokenForChannel($channelCode));
        if (! $token) {
            throw ValidationException::withMessages(['session' => __('auth.unauthorized')]);
        }

        if (! in_array($purpose, [AttachmentPurpose::ConversationImage, AttachmentPurpose::ConversationFile], true)) {
            throw ValidationException::withMessages(['purpose' => __('auth.unauthorized')]);
        }

        $channel = Channel::query()
            ->where('code', $channelCode)
            ->where('type', ChannelType::Web)
            ->firstOrFail();

        $activeUploads = Attachment::query()
            ->where('session_token_hash', hash('sha256', $token))
            ->whereIn('status', [AttachmentStatus::Pending, AttachmentStatus::Uploaded])
            ->whereNull('attachable_id')
            ->count();
        if ($activeUploads >= self::MAX_ACTIVE_VISITOR_UPLOADS) {
            Log::warning('[attachment] 访客未绑定附件达到上限', [
                'channel_id' => (string) $channel->id,
                'active_uploads' => $activeUploads,
            ]);

            throw ValidationException::withMessages([
                'attachment' => __('attachments.errors.too_many_active_uploads'),
            ]);
        }

        return [null, $token];
    }
}
