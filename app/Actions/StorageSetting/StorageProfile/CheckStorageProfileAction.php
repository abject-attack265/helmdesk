<?php

namespace App\Actions\StorageSetting\StorageProfile;

use App\Models\StorageProfile;
use App\Services\Storage\StorageCorsChecker;
use App\Services\Storage\StorageProfileDisk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class CheckStorageProfileAction
{
    use AsAction;

    public function __construct(
        private readonly StorageCorsChecker $corsChecker,
    ) {}

    public function handle(StorageProfile $profile, string $origin): void
    {
        try {
            $disk = StorageProfileDisk::build($profile);
            $path = 'health-check/'.str()->ulid().'.txt';
            $disk->put($path, 'ok');
            $disk->size($path);
            $disk->delete($path);
        } catch (Throwable $exception) {
            Log::warning('Storage profile connection check failed', [
                'storage_profile_id' => $profile->id,
                'exception' => $exception,
            ]);

            throw ValidationException::withMessages([
                'profile' => __('storage_settings.connection_check_failed'),
            ]);
        }

        $this->corsChecker->assertSupportsBrowserUploads($profile, $origin);
    }

    public function asController(Request $request, StorageProfile $profile): RedirectResponse
    {
        try {
            $this->handle($profile, StorageCorsChecker::browserOriginFromRequest($request));
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

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('storage_settings.connection_check_success'),
        ]);

        return back();
    }
}
