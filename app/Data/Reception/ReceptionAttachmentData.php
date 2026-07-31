<?php

namespace App\Data\Reception;

use App\Models\Attachment;
use App\Services\Storage\AttachmentUrlResolver;
use Spatie\LaravelData\Data;

/**
 * 访客端会话消息中的附件数据，挂在 ReceptionMessageData 上，供访客 SPA 渲染图片预览与文件下载入口。
 */
class ReceptionAttachmentData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $mime_type,
        public int $byte_size,
        public string $url,
    ) {}

    public static function fromModel(Attachment $attachment): self
    {
        return new self(
            id: (string) $attachment->id,
            name: $attachment->original_name,
            mime_type: $attachment->mime_type,
            byte_size: $attachment->byte_size,
            url: app(AttachmentUrlResolver::class)->url($attachment),
        );
    }
}
