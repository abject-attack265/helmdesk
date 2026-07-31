<?php

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Models\StorageProfile;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class StorageProfileDisk
{
    public static function build(StorageProfile $profile): FilesystemAdapter
    {
        if ($profile->driver === StorageDriver::Local) {
            return Storage::disk('local');
        }

        return Storage::build($profile->s3FilesystemConfig());
    }

    public static function buildForUpload(StorageProfile $profile): FilesystemAdapter
    {
        if ($profile->driver === StorageDriver::Local) {
            return Storage::disk('local');
        }

        return Storage::build($profile->uploadS3FilesystemConfig());
    }
}
