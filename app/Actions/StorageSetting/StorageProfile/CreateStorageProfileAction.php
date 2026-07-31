<?php

namespace App\Actions\StorageSetting\StorageProfile;

use App\Data\StorageSetting\FormCreateStorageProfileData;
use App\Enums\StorageDriver;
use App\Enums\StorageProfileStatus;
use App\Models\StorageProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateStorageProfileAction
{
    use AsAction;

    public function handle(FormCreateStorageProfileData $data): StorageProfile
    {
        return StorageProfile::query()->create([
            'name' => $data->name,
            'driver' => StorageDriver::S3,
            'provider' => $data->provider,
            'status' => StorageProfileStatus::Active,
            'region' => $data->region,
            'endpoint' => $data->endpoint,
            'upload_endpoint' => $data->uploadEndpoint,
            'bucket' => $data->bucket,
            'access_key' => $data->key,
            'secret_key' => $data->secret,
            'public_url' => $data->url,
            'metadata' => [],
        ]);
    }

    public function asController(Request $request): RedirectResponse
    {
        $this->handle(FormCreateStorageProfileData::from($request));

        return redirect()->route('app.manage.storage.index');
    }
}
