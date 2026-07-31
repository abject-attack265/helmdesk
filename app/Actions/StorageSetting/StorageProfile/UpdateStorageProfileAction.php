<?php

namespace App\Actions\StorageSetting\StorageProfile;

use App\Data\StorageSetting\FormUpdateStorageProfileData;
use App\Models\StorageProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateStorageProfileAction
{
    use AsAction;

    public function handle(StorageProfile $profile, FormUpdateStorageProfileData $data): void
    {
        $update = [
            'name' => $data->name,
            'public_url' => $data->url,
        ];

        $key = filled($data->key) ? $data->key : null;
        $secret = filled($data->secret) ? $data->secret : null;
        if ($key !== null || $secret !== null) {
            if ($key === null || $secret === null) {
                throw ValidationException::withMessages([
                    'secret' => __('storage_settings.profile_credentials_pair_required'),
                ]);
            }

            $update['access_key'] = $key;
            $update['secret_key'] = $secret;
        }

        $profile->update($update);
    }

    public function asController(Request $request, StorageProfile $profile): RedirectResponse
    {
        $this->handle($profile, FormUpdateStorageProfileData::from($request));

        return redirect()->route('app.manage.storage.index');
    }
}
