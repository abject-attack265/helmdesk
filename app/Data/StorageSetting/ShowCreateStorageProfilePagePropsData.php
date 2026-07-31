<?php

namespace App\Data\StorageSetting;

use Spatie\LaravelData\Data;

class ShowCreateStorageProfilePagePropsData extends Data
{
    public function __construct(
        /** @var list<StorageProviderConfigData> */
        public array $providers,
    ) {}
}
