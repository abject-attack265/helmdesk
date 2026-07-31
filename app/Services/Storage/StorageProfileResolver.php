<?php

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Enums\StorageProfileStatus;
use App\Models\StorageProfile;
use App\Settings\StorageSettings;
use Illuminate\Validation\ValidationException;

class StorageProfileResolver
{
    public function __construct(
        private readonly StorageSettings $settings,
    ) {}

    public function resolveForNewUpload(): StorageProfile
    {
        if (! $this->settings->enabled) {
            return $this->localProfile();
        }

        if (! filled($this->settings->current_profile_id)) {
            throw ValidationException::withMessages([
                'storage' => __('storage_settings.current_profile_required'),
            ]);
        }

        $profile = StorageProfile::query()
            ->where('status', StorageProfileStatus::Active)
            ->find($this->settings->current_profile_id);

        if (! $profile) {
            throw ValidationException::withMessages([
                'storage' => __('storage_settings.current_profile_missing'),
            ]);
        }

        return $profile;
    }

    public function localProfile(): StorageProfile
    {
        return StorageProfile::query()->firstOrCreate(
            [
                'driver' => StorageDriver::Local,
                'provider' => null,
                'name' => 'Local storage',
            ],
            [
                'status' => StorageProfileStatus::Active,
                'metadata' => ['system' => true],
            ],
        );
    }
}
