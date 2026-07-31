<?php

namespace App\Data\StorageSetting;

use App\Enums\StorageProvider;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class FormCheckStorageSettingData extends Data
{
    public function __construct(
        public string $provider,
        public string $key,
        public ?string $secret,
        public string $bucket,
        public string $region,
        public string $endpoint,
        public ?string $url = null,
        public ?string $uploadEndpoint = null,
    ) {}

    public static function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::enum(StorageProvider::class)],
            'key' => ['required', 'string'],
            'secret' => ['nullable', 'string'],
            'bucket' => ['required', 'string'],
            'region' => ['required', 'string'],
            'endpoint' => ['required', 'string', 'url'],
            'upload_endpoint' => ['nullable', 'string', 'url'],
            'url' => ['nullable', 'string', 'url'],
        ];
    }
}
