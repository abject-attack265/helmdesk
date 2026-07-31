<?php

namespace App\Data\StorageSetting;

use Spatie\LaravelData\Data;

class ShowGetStorageSettingPagePropsData extends Data
{
    public function __construct(
        public StorageSettingData $settings,
        /** @var list<StorageProfileData> */
        public array $profiles,
        /** @var list<StorageProviderConfigData> */
        public array $providers,
    ) {}
}
