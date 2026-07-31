<?php

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Models\Attachment;

class AttachmentUrlResolver
{
    public function url(Attachment $attachment): string
    {
        $profile = $attachment->storageProfile;

        if ($profile->driver === StorageDriver::Local) {
            return route('attachments.content', ['attachment' => $attachment->id]);
        }

        return StorageProfileDisk::build($profile)->url($attachment->object_key);
    }
}
