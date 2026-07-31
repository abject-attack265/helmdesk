<?php

namespace App\Actions\Attachment;

use App\Enums\AttachmentStatus;
use App\Models\Attachment;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 删除附件对象和附件记录。
 */
class DeleteAttachmentAction
{
    use AsAction;

    /**
     * 删除附件文件和数据库记录。
     */
    public function handle(Attachment $attachment): bool
    {
        $attachment->filesystem()->delete($attachment->object_key);
        if (filled($attachment->upload_object_key)
            && $attachment->upload_object_key !== $attachment->object_key) {
            $attachment->filesystem()->delete($attachment->upload_object_key);
        }

        $attachment->status = AttachmentStatus::Deleted;
        $attachment->upload_object_key = null;
        $attachment->upload_expires_at = null;
        $attachment->save();

        return (bool) $attachment->delete();
    }
}
