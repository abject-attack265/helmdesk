<?php

namespace App\Models;

use App\Enums\StorageDriver;
use App\Enums\StorageProfileStatus;
use App\Enums\StorageProvider;
use Database\Factories\StorageProfileFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorageProfile extends Model
{
    /** @use HasFactory<StorageProfileFactory> */
    use HasFactory, HasUlids;

    protected $table = 'storage_profiles';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'driver' => StorageDriver::class,
            'provider' => StorageProvider::class,
            'status' => StorageProfileStatus::class,
            'access_key' => 'encrypted',
            'secret_key' => 'encrypted',
            'session_token' => 'encrypted',
            'force_path_style' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /** @return array<string, mixed> */
    public function s3FilesystemConfig(?string $endpoint = null): array
    {
        return $this->withoutNullValues([
            'driver' => 's3',
            'key' => $this->access_key,
            'secret' => $this->secret_key,
            'token' => $this->session_token,
            'region' => $this->region ?: 'us-east-1',
            'bucket' => $this->bucket,
            'endpoint' => $endpoint ?? $this->endpoint,
            'url' => $this->public_url,
            'use_path_style_endpoint' => $this->force_path_style,
            'throw' => true,
        ]);
    }

    public function uploadS3FilesystemConfig(): array
    {
        return $this->s3FilesystemConfig($this->upload_endpoint);
    }

    private function withoutNullValues(array $config): array
    {
        return array_filter($config, static fn (mixed $value): bool => $value !== null);
    }
}
