<?php

namespace App\Actions\StorageSetting;

use App\Data\StorageSetting\FormCheckStorageSettingData;
use App\Enums\StorageDriver;
use App\Enums\StorageProfileStatus;
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

class CheckStorageSettingAction
{
    use AsAction;

    public function __construct(
        private readonly StorageCorsChecker $corsChecker,
    ) {}

    public function handle(FormCheckStorageSettingData $data, string $origin): void
    {
        if (! filled($data->secret)) {
            throw ValidationException::withMessages([
                'secret' => __('storage_settings.secret_required'),
            ]);
        }

        $profile = new StorageProfile([
            'driver' => StorageDriver::S3,
            'provider' => $data->provider,
            'status' => StorageProfileStatus::Active,
            'access_key' => $data->key,
            'secret_key' => $data->secret,
            'bucket' => $data->bucket,
            'region' => $data->region,
            'endpoint' => $data->endpoint,
            'upload_endpoint' => $data->uploadEndpoint,
            'public_url' => $data->url,
            'force_path_style' => false,
        ]);

        try {
            $disk = StorageProfileDisk::build($profile);
            $path = 'health-check/'.str()->ulid().'.txt';
            $disk->put($path, 'ok');
            $disk->size($path);
            $disk->delete($path);
        } catch (Throwable $exception) {
            Log::warning('Storage connection check failed', [
                'provider' => $data->provider,
                'region' => $data->region,
                'endpoint' => $data->endpoint,
                'bucket' => $data->bucket,
                'exception' => $exception,
            ]);

            throw ValidationException::withMessages([
                'secret' => __('storage_settings.validation_failed'),
            ]);
        }

        $this->corsChecker->assertSupportsBrowserUploads($profile, $origin);
    }

    public function asController(Request $request): RedirectResponse
    {
        $data = FormCheckStorageSettingData::from($request);

        try {
            $this->handle($data, StorageCorsChecker::browserOriginFromRequest($request));
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())
                ->flatten()
                ->unique()
                ->implode("\n");

            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => $message !== '' ? $message : __('storage_settings.validation_failed'),
            ]);

            return back()->withErrors($exception->errors());
        } catch (Throwable $exception) {
            Log::warning('Storage connection check failed', [
                'provider' => $data->provider,
                'region' => $data->region,
                'endpoint' => $data->endpoint,
                'bucket' => $data->bucket,
                'exception' => $exception,
            ]);

            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('storage_settings.validation_failed'),
            ]);

            return back()->withErrors([
                'secret' => __('storage_settings.validation_failed'),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('storage_settings.check_success'),
        ]);

        return back();
    }
}
