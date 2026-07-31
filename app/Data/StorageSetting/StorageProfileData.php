<?php

namespace App\Data\StorageSetting;

use App\Data\EnumOptionData;
use App\Models\StorageProfile;
use Spatie\LaravelData\Data;

class StorageProfileData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $driver,
        public string $status,
        public ?EnumOptionData $provider,
        public ?string $bucket,
        public ?string $region,
        public ?string $endpoint,
        public ?string $uploadEndpoint,
        public ?string $url,
        public ?string $keyMasked,
        public bool $hasSecret,
    ) {}

    public static function fromModel(StorageProfile $profile): self
    {
        $key = filled($profile->access_key) ? (string) $profile->access_key : null;
        $masked = $key === null
            ? null
            : (strlen($key) <= 8
                ? str_repeat('*', max(strlen($key), 4))
                : substr($key, 0, 4).'****'.substr($key, -4));

        return new self(
            id: (string) $profile->id,
            name: (string) $profile->name,
            driver: $profile->driver->value,
            status: $profile->status->value,
            provider: $profile->provider ? EnumOptionData::fromEnum($profile->provider) : null,
            bucket: $profile->bucket,
            region: $profile->region,
            endpoint: $profile->endpoint,
            uploadEndpoint: $profile->upload_endpoint,
            url: $profile->public_url,
            keyMasked: $masked,
            hasSecret: filled($profile->secret_key),
        );
    }
}
