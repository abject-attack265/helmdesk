<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

enum StorageProfileStatus: string implements LabeledEnum
{
    case Active = 'active';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('storage_settings.status.active'),
            self::Disabled => __('storage_settings.status.disabled'),
        };
    }
}
