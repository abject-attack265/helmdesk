<?php

namespace App\Actions\Attachment;

use App\Data\Attachment\UploadedAttachmentData;
use App\Enums\AttachmentStatus;
use App\Models\Attachment;
use App\Services\Storage\AttachmentAccessContext;
use App\Services\Storage\AttachmentMimeDetector;
use App\Services\Storage\AttachmentObjectPromoter;
use App\Services\Storage\AttachmentPathGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 完成附件直传：校验直传对象、按真实内容复核类型，翻为 uploaded。
 *
 * 只读对象头部若干字节做内容嗅探（防伪装），大头字节始终留在对象存储、不过应用。
 */
class FinalizeAttachmentUploadAction
{
    use AsAction;

    /**
     * 注入上传规则校验、真实类型识别、路径生成与对象提升服务。
     */
    public function __construct(
        private readonly ValidateAttachmentUploadAction $uploadValidator,
        private readonly AttachmentMimeDetector $mimeDetector,
        private readonly AttachmentPathGenerator $pathGenerator,
        private readonly AttachmentObjectPromoter $objectPromoter,
    ) {}

    /**
     * 校验直传对象与归属，按真实内容复核后翻为 uploaded。
     */
    public function handle(Attachment $attachment, AttachmentAccessContext $accessContext): Attachment
    {
        if (! $this->controlsUpload($accessContext, $attachment)) {
            abort(403);
        }

        if ($attachment->status !== AttachmentStatus::Pending) {
            throw ValidationException::withMessages(['attachment' => __('attachments.errors.invalid_upload_mode')]);
        }

        if ($attachment->expires_at !== null && $attachment->expires_at->isPast()) {
            throw ValidationException::withMessages(['attachment' => __('attachments.errors.upload_expired')]);
        }

        $disk = $attachment->filesystem();
        if (! $disk->exists($attachment->object_key)) {
            throw ValidationException::withMessages(['attachment' => __('attachments.errors.object_missing')]);
        }

        $byteSize = $disk->size($attachment->object_key);
        $realMime = $this->sniffMime($attachment);

        try {
            if ($realMime === null) {
                throw ValidationException::withMessages([
                    'mime_type' => __('attachments.errors.object_mime_mismatch'),
                ]);
            }

            $this->uploadValidator->handle($attachment->purpose, $realMime, $byteSize);
        } catch (ValidationException $e) {
            $disk->delete($attachment->object_key);
            $attachment->update(['status' => AttachmentStatus::Failed]);
            throw $e;
        }

        $attachment->forceFill([
            'mime_type' => $realMime,
            'extension' => $this->pathGenerator->extension($attachment->original_name, $realMime),
        ]);
        $finalObjectKey = $this->pathGenerator->generate(
            (string) $attachment->id,
            $attachment->purpose,
            $attachment->original_name,
            $realMime,
        );
        $this->objectPromoter->promote($attachment, $finalObjectKey);

        $attachment->update([
            'object_key' => $finalObjectKey,
            'status' => AttachmentStatus::Uploaded,
            'mime_type' => $realMime,
            'extension' => $attachment->extension,
            'byte_size' => $byteSize,
            'uploaded_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        return $attachment->fresh() ?? $attachment;
    }

    /**
     * 接收 finalize 请求并返回可展示、可后续绑定的附件数据。
     */
    public function asController(Request $request, Attachment $attachment): JsonResponse
    {
        $attachment = $this->handle($attachment, AttachmentAccessContext::fromRequest($request));

        return response()->json([
            'attachment' => UploadedAttachmentData::fromModel($attachment)->toArray(),
        ]);
    }

    /**
     * 判断请求方是否拥有该占位附件的直传控制权（上传者本人或同一访客会话）。
     */
    private function controlsUpload(AttachmentAccessContext $accessContext, Attachment $attachment): bool
    {
        if (filled($attachment->uploaded_by_user_id)) {
            foreach ($accessContext->users as $user) {
                if ((string) $attachment->uploaded_by_user_id === (string) $user->id) {
                    return true;
                }
            }

            return false;
        }

        if (! filled($attachment->session_token_hash)) {
            return false;
        }

        foreach ($accessContext->visitorTokens() as $token) {
            if (hash_equals((string) $attachment->session_token_hash, hash('sha256', $token))) {
                return true;
            }
        }

        return false;
    }

    /**
     * 只读对象头部若干字节，按内容嗅探真实 MIME。
     */
    private function sniffMime(Attachment $attachment): ?string
    {
        $stream = $attachment->filesystem()->readStream($attachment->object_key);
        if (! is_resource($stream)) {
            return null;
        }

        try {
            return $this->mimeDetector->detectStream($stream);
        } finally {
            fclose($stream);
        }
    }
}
