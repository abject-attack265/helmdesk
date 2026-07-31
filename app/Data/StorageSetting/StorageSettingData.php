<?php

namespace App\Data\StorageSetting;

use Spatie\LaravelData\Data;

class StorageSettingData extends Data
{
    public function __construct(
        public bool $enabled,
        public ?string $current_profile_id,
    ) {}
}
