<?php

namespace App\Services\Storage;

use App\Actions\Attachment\AttachUploadedAttachmentsAction;
use App\Actions\Attachment\DeleteAttachmentAction;
use App\Enums\AttachmentPurpose;
use App\Enums\AttachmentStatus;
use App\Exceptions\BusinessException;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * 附件绑定服务，封装可绑定性校验、替换清理和资源归属同步。
 */
class AttachmentBindingService
{
    /**
     * 注入附件绑定与删除 Action。
     */
    public function __construct(
        private readonly AttachUploadedAttachmentsAction $attachUploadedAttachments,
        private readonly DeleteAttachmentAction $deleteAttachment,
    ) {}

    /**
     * 校验附件是否可绑定到指定模型。
     *
     * @param  list<AttachmentPurpose>  $allowedPurposes
     */
    public function assertAssignable(
        Model $attachable,
        ?string $attachmentId,
        ?string $currentAttachmentId,
        User $actor,
        array $allowedPurposes,
        string $messageKey,
    ): void {
        if (! filled($attachmentId)) {
            return;
        }

        if ($currentAttachmentId !== null && $currentAttachmentId === $attachmentId) {
            return;
        }

        try {
            $this->attachUploadedAttachments->assertCanAttach(
                attachable: $attachable,
                attachmentId: $attachmentId,
                actor: $actor,
                allowedPurposes: $allowedPurposes,
            );
        } catch (ValidationException) {
            throw new BusinessException(__($messageKey));
        }
    }

    /**
     * 按字段值同步附件绑定，并在替换时删除旧附件。
     */
    public function syncAttachment(Model $attachable, string $column, ?string $originalId, User $actor): void
    {
        $value = $attachable->getAttribute($column);
        $attachmentId = filled($value) ? (string) $value : null;

        if ($originalId !== null && $originalId !== $attachmentId) {
            $oldAttachment = Attachment::query()->find($originalId);
            if ($oldAttachment instanceof Attachment) {
                $this->deleteAttachment->handle($oldAttachment);
            }
        }

        if ($attachmentId === null) {
            return;
        }

        $attachment = Attachment::query()->find($attachmentId);
        if (! $attachment instanceof Attachment) {
            return;
        }

        $this->attachUploadedAttachments->handle($attachable, (string) $attachment->id, actor: $actor);
    }

    /**
     * 校验设置中的附件引用。
     *
     * @param  list<AttachmentPurpose>  $allowedPurposes
     */
    public function assertSettingsAttachmentAssignable(
        ?string $attachmentId,
        ?string $currentAttachmentId,
        User $actor,
        array $allowedPurposes,
        string $messageKey,
    ): void {
        if (! filled($attachmentId) || $attachmentId === $currentAttachmentId) {
            return;
        }

        try {
            $this->attachUploadedAttachments->assertCanAttachToSettings(
                attachmentId: $attachmentId,
                actor: $actor,
                allowedPurposes: $allowedPurposes,
            );
        } catch (ValidationException) {
            throw new BusinessException(__($messageKey));
        }
    }

    /**
     * 更新设置中的附件引用并清理旧文件。
     */
    public function syncSettingsAttachment(?string $attachmentId, ?string $originalId, User $actor): void
    {
        $nextId = filled($attachmentId) ? (string) $attachmentId : null;

        if ($originalId !== null && $originalId !== $nextId) {
            $oldAttachment = Attachment::query()->find($originalId);
            if ($oldAttachment instanceof Attachment) {
                $this->deleteAttachment->handle($oldAttachment);
            }
        }

        if ($nextId === null) {
            return;
        }

        $attachment = $this->attachUploadedAttachments->assertCanAttachToSettings($nextId, $actor);
        $attachment->update([
            'status' => AttachmentStatus::Attached,
            'attached_at' => now(),
            'expires_at' => null,
        ]);
    }
}
