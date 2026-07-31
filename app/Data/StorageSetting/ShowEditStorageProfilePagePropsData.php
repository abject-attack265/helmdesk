<?php

namespace App\Data\StorageSetting;

use Spatie\LaravelData\Data;

class ShowEditStorageProfilePagePropsData extends Data
{
    public function __construct(
        public StorageProfileData $profile,
        /** @var list<StorageProviderConfigData> */
        public array $providers,
    ) {}
}
