<?php

namespace App\Data\StorageSetting;

use App\Enums\StorageProvider;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class FormCreateStorageProfileData extends Data
{
    public function __construct(
        public string $name,
        public string $provider,
        public string $region,
        public string $endpoint,
        public string $bucket,
        public string $key,
        public string $secret,
        public ?string $uploadEndpoint = null,
        public ?string $url = null,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'provider' => ['required', 'string', Rule::enum(StorageProvider::class)],
            'region' => ['required', 'string'],
            'endpoint' => ['required', 'string', 'url'],
            'upload_endpoint' => ['nullable', 'string', 'url'],
            'bucket' => ['required', 'string'],
            'key' => ['required', 'string'],
            'secret' => ['required', 'string'],
            'url' => ['nullable', 'string', 'url'],
        ];
    }
}
