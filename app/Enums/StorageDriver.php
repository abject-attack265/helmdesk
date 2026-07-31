<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

enum StorageDriver: string implements LabeledEnum
{
    case Local = 'local';
    case S3 = 's3';

    public function label(): string
    {
        return match ($this) {
            self::Local => __('storage_settings.drivers.local'),
            self::S3 => __('storage_settings.drivers.s3'),
        };
    }
}
