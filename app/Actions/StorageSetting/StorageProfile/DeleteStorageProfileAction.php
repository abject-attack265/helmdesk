<?php

namespace App\Actions\StorageSetting\StorageProfile;

use App\Models\Attachment;
use App\Models\StorageProfile;
use App\Settings\StorageSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteStorageProfileAction
{
    use AsAction;

    public function __construct(
        private readonly StorageSettings $settings,
    ) {}

    public function handle(StorageProfile $profile): void
    {
        if ($this->settings->enabled && $this->settings->current_profile_id === (string) $profile->id) {
            throw ValidationException::withMessages([
                'profile' => __('storage_settings.profile_is_active_cannot_delete'),
            ]);
        }

        if (Attachment::withTrashed()->where('storage_profile_id', $profile->id)->exists()) {
            throw ValidationException::withMessages([
                'profile' => __('storage_settings.profile_is_referenced_cannot_delete'),
            ]);
        }

        $profile->delete();
    }

    public function asController(Request $request, StorageProfile $profile): RedirectResponse
    {
        $this->handle($profile);

        return back();
    }
}
