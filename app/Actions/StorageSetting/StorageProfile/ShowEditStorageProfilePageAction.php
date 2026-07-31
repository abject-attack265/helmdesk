<?php

namespace App\Actions\StorageSetting\StorageProfile;

use App\Data\StorageSetting\ShowEditStorageProfilePagePropsData;
use App\Data\StorageSetting\StorageProfileData;
use App\Data\StorageSetting\StorageProviderConfigData;
use App\Enums\StorageProvider;
use App\Models\StorageProfile;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowEditStorageProfilePageAction
{
    use AsAction;

    public function handle(StorageProfile $profile): ShowEditStorageProfilePagePropsData
    {
        return new ShowEditStorageProfilePagePropsData(
            profile: StorageProfileData::fromModel($profile),
            providers: array_map(
                static fn (StorageProvider $provider): StorageProviderConfigData => StorageProviderConfigData::fromProvider($provider),
                StorageProvider::cases(),
            ),
        );
    }

    public function asController(StorageProfile $profile): Response
    {
        return Inertia::render(
            'appSettings/storageSetting/Edit',
            $this->handle($profile)->toArray(),
        );
    }
}
