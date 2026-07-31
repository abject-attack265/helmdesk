<?php

namespace App\Actions\StorageSetting\StorageProfile;

use App\Data\StorageSetting\ShowCreateStorageProfilePagePropsData;
use App\Data\StorageSetting\StorageProviderConfigData;
use App\Enums\StorageProvider;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowCreateStorageProfilePageAction
{
    use AsAction;

    public function handle(): ShowCreateStorageProfilePagePropsData
    {
        return new ShowCreateStorageProfilePagePropsData(
            providers: array_map(
                static fn (StorageProvider $provider): StorageProviderConfigData => StorageProviderConfigData::fromProvider($provider),
                StorageProvider::cases(),
            ),
        );
    }

    public function asController(): Response
    {
        return Inertia::render('appSettings/storageSetting/Create', $this->handle()->toArray());
    }
}
