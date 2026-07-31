<?php

namespace Database\Factories;

use App\Enums\StorageDriver;
use App\Enums\StorageProfileStatus;
use App\Models\StorageProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageProfile>
 */
class StorageProfileFactory extends Factory
{
    protected $model = StorageProfile::class;

    public function definition(): array
    {
        return [
            'name' => 'Local storage',
            'driver' => StorageDriver::Local,
            'provider' => null,
            'status' => StorageProfileStatus::Active,
            'metadata' => ['system' => true],
        ];
    }

    public function s3(): static
    {
        return $this->state([
            'name' => 'Object storage',
            'driver' => StorageDriver::S3,
            'provider' => 'generic',
            'access_key' => 'access-key',
            'secret_key' => 'secret-key',
            'bucket' => 'bucket',
            'region' => 'us-east-1',
            'endpoint' => 'https://s3.example.com',
            'upload_endpoint' => null,
            'public_url' => 'https://cdn.example.com',
            'metadata' => [],
        ]);
    }
}
