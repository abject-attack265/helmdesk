<?php

namespace App\Data\StorageSetting;

use Spatie\LaravelData\Data;

class StorageRegionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $endpoint,
        public ?string $internalEndpoint = null,
    ) {}
}
