<?php

namespace App\Services\Storage;

use App\Models\Attachment;
use Illuminate\Filesystem\AwsS3V3Adapter;
use RuntimeException;

/**
 * 将校验通过的临时上传对象提升为不可覆盖的最终对象。
 */
class AttachmentObjectPromoter
{
    /**
     * 把附件对象移动到最终 key，并为 S3 对象写入公开访问元数据。
     */
    public function promote(Attachment $attachment, string $finalObjectKey): void
    {
        $disk = $attachment->filesystem();

        if ($disk instanceof AwsS3V3Adapter) {
            $this->promoteS3Object($disk, $attachment, $finalObjectKey);

            return;
        }

        if (! $disk->move($attachment->object_key, $finalObjectKey)) {
            throw new RuntimeException('附件临时对象提升失败。');
        }
    }

    /**
     * 复制 S3 临时对象到最终 key、写入响应元数据并删除临时对象。
     */
    private function promoteS3Object(
        AwsS3V3Adapter $disk,
        Attachment $attachment,
        string $finalObjectKey,
    ): void {
        $client = $disk->getClient();
        $bucket = $disk->getConfig()['bucket'];

        $client->copyObject([
            'Bucket' => $bucket,
            'Key' => $finalObjectKey,
            'CopySource' => $bucket.'/'.$attachment->object_key,
            'MetadataDirective' => 'REPLACE',
            'ContentType' => $attachment->mime_type,
            'ContentDisposition' => Attachment::dispositionFor($attachment->mime_type, $attachment->original_name),
            'CacheControl' => Attachment::CACHE_CONTROL,
        ]);

        if (! $disk->delete($attachment->object_key)) {
            throw new RuntimeException('附件临时对象删除失败。');
        }
    }
}
