<?php

namespace App\Data\Attachment;

use App\Enums\AttachmentStatus;
use App\Models\Attachment;
use Spatie\LaravelData\Data;

/**
 * 已创建或已完成上传的附件响应数据，供前端预览和后续表单绑定使用。
 */
class UploadedAttachmentData extends Data
{
    /**
     * 承载前端表单和预览组件需要的附件基础信息。
     */
    public function __construct(
        public string $id,
        public AttachmentStatus $status,
        public string $name,
        public string $mime_type,
        public int $byte_size,
        public ?string $full_url = null,
    ) {}

    /**
     * 从附件模型创建完整的可展示响应数据。
     */
    public static function fromModel(Attachment $attachment, bool $withUrls = true): self
    {
        return new self(
            id: (string) $attachment->id,
            status: $attachment->status,
            name: $attachment->original_name,
            mime_type: $attachment->mime_type,
            byte_size: $attachment->byte_size,
            full_url: $withUrls ? $attachment->full_url : null,
        );
    }
}
