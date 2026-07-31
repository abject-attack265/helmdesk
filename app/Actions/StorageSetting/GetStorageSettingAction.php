<?php

namespace App\Actions\StorageSetting;

use App\Data\StorageSetting\ShowGetStorageSettingPagePropsData;
use App\Data\StorageSetting\StorageProfileData;
use App\Data\StorageSetting\StorageProviderConfigData;
use App\Data\StorageSetting\StorageSettingData;
use App\Enums\StorageDriver;
use App\Enums\StorageProvider;
use App\Models\StorageProfile;
use App\Settings\StorageSettings;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStorageSettingAction
{
    use AsAction;

    public function __construct(
        private readonly StorageSettings $settings,
    ) {}

    public function handle(): ShowGetStorageSettingPagePropsData
    {
        $profiles = StorageProfile::query()
            ->where('driver', StorageDriver::S3)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StorageProfile $profile): StorageProfileData => StorageProfileData::fromModel($profile))
            ->all();

        $providers = array_map(
            static fn (StorageProvider $provider): StorageProviderConfigData => StorageProviderConfigData::fromProvider($provider),
            StorageProvider::cases(),
        );

        return new ShowGetStorageSettingPagePropsData(
            settings: new StorageSettingData(
                enabled: $this->settings->enabled,
                current_profile_id: $this->settings->current_profile_id,
            ),
            profiles: $profiles,
            providers: $providers,
        );
    }

    public function asController(): Response
    {
        return Inertia::render('appSettings/storageSetting/Index', $this->handle()->toArray());
    }
}
