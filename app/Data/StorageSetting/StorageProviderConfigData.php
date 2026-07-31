<?php

namespace App\Data\StorageSetting;

use App\Data\EnumOptionData;
use App\Enums\StorageProvider;
use Spatie\LaravelData\Data;

class StorageProviderConfigData extends Data
{
    public function __construct(
        public EnumOptionData $provider,
        public string $helpLink,
        /** @var list<StorageRegionData> */
        public array $regions,
    ) {}

    public static function fromProvider(StorageProvider $provider): self
    {
        return new self(
            provider: EnumOptionData::fromEnum($provider),
            helpLink: $provider->getHelpLink(),
            regions: array_map(
                static fn (array $region): StorageRegionData => StorageRegionData::from($region),
                $provider->getRegions(),
            ),
        );
    }
}
