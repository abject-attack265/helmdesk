<?php

namespace App\Actions\StorageSetting;

use App\Data\StorageSetting\FormStorageSettingData;
use App\Models\StorageProfile;
use App\Services\Storage\StorageCorsChecker;
use App\Services\Storage\StorageProfileDisk;
use App\Settings\StorageSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class UpdateStorageSettingAction
{
    use AsAction;

    public function __construct(
        private readonly StorageSettings $settings,
        private readonly StorageCorsChecker $corsChecker,
    ) {}

    public function handle(FormStorageSettingData $data, string $origin): void
    {
        if (! $data->enabled) {
            $this->settings->enabled = false;
            $this->settings->current_profile_id = null;
            $this->settings->save();

            return;
        }

        $profile = StorageProfile::query()
            ->where('status', 'active')
            ->find($data->current_profile_id);

        if (! $profile) {
            throw ValidationException::withMessages([
                'current_profile_id' => __('storage_settings.storage_not_found'),
            ]);
        }

        if (! filled($profile->access_key) || ! filled($profile->secret_key)) {
            throw ValidationException::withMessages([
                'current_profile_id' => __('storage_settings.storage_key_secret_required'),
            ]);
        }

        try {
            $disk = StorageProfileDisk::build($profile);
            $path = 'health-check/'.str()->ulid().'.txt';
            $disk->put($path, 'ok');
            $disk->size($path);
            $disk->delete($path);
        } catch (Throwable $exception) {
            Log::warning('Storage profile connection check failed during save', [
                'storage_profile_id' => $profile->id,
                'exception' => $exception,
            ]);

            throw ValidationException::withMessages([
                'current_profile_id' => __('storage_settings.connection_check_failed'),
            ]);
        }

        $this->corsChecker->assertSupportsBrowserUploads($profile, $origin);

        $this->settings->enabled = true;
        $this->settings->current_profile_id = (string) $profile->id;
        $this->settings->save();
    }

    public function asController(Request $request): RedirectResponse
    {
        try {
            $this->handle(
                FormStorageSettingData::from($request),
                StorageCorsChecker::browserOriginFromRequest($request),
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())
                ->flatten()
                ->unique()
                ->implode("\n");

            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => $message !== '' ? $message : __('storage_settings.connection_check_failed'),
            ]);

            return back()->withErrors($exception->errors());
        }

        return back();
    }
}
