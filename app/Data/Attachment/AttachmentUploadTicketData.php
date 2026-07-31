<?php

namespace App\Data\Attachment;

use Spatie\LaravelData\Data;

/**
 * 直传签名票据：前端拿到后用 upload_url（presigned PUT）把文件直传到对象存储，
 * 再用 attachment_id 调 finalize 完成。字节不经过应用。
 */
class AttachmentUploadTicketData extends Data
{
    /**
     * @param  array<string, string>  $upload_headers  直传 PUT 必须带上的请求头
     */
    public function __construct(
        public string $attachment_id,
        public string $upload_url,
        public array $upload_headers,
        public string $expires_at,
    ) {}
}
