<?php

namespace App\Services\Storage;

use App\Enums\StorageDriver;
use App\Models\StorageProfile;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class StorageCorsChecker
{
    public static function browserOriginFromRequest(Request $request): string
    {
        $origin = $request->headers->get('Origin');

        return is_string($origin) && $origin !== ''
            ? $origin
            : $request->getSchemeAndHttpHost();
    }

    public function assertSupportsBrowserUploads(StorageProfile $profile, string $origin): void
    {
        if ($profile->driver === StorageDriver::Local) {
            return;
        }

        $disk = StorageProfileDisk::buildForUpload($profile);
        if (! $disk instanceof AwsS3V3Adapter) {
            throw ValidationException::withMessages([
                'endpoint' => __('storage_settings.cors_check_failed'),
            ]);
        }

        try {
            $rules = $disk->getClient()->getBucketCors([
                'Bucket' => $profile->bucket,
            ])->get('CORSRules') ?? [];
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'endpoint' => __('storage_settings.cors_check_failed'),
            ]);
        }

        $origin = rtrim($origin, '/');
        if (! is_array($rules) || $rules === []) {
            throw ValidationException::withMessages([
                'endpoint' => __('storage_settings.cors_direct_upload_required', ['origin' => $origin]),
            ]);
        }

        $allowsPut = $this->hasMatchingRule($rules, $origin, 'PUT', ['content-type'], []);
        if (! $allowsPut) {
            throw ValidationException::withMessages([
                'endpoint' => __('storage_settings.cors_direct_upload_required', ['origin' => $origin]),
            ]);
        }
    }

    private function hasMatchingRule(
        array $rules,
        string $origin,
        string $method,
        array $requiredHeaders,
        array $requiredExposeHeaders,
    ): bool {
        foreach ($rules as $rule) {
            if (
                $this->originMatches($this->toStringList($rule['AllowedOrigins'] ?? []), $origin)
                && $this->methodMatches($this->toStringList($rule['AllowedMethods'] ?? []), $method)
                && $this->headersMatch($this->toStringList($rule['AllowedHeaders'] ?? []), $requiredHeaders)
                && $this->headersMatch($this->toStringList($rule['ExposeHeaders'] ?? []), $requiredExposeHeaders)
            ) {
                return true;
            }
        }

        return false;
    }

    private function originMatches(array $allowedOrigins, string $origin): bool
    {
        foreach ($allowedOrigins as $allowedOrigin) {
            $allowedOrigin = rtrim($allowedOrigin, '/');

            if ($allowedOrigin === '*' || $allowedOrigin === $origin) {
                return true;
            }

            if (str_contains($allowedOrigin, '*') && fnmatch($allowedOrigin, $origin)) {
                return true;
            }
        }

        return false;
    }

    private function methodMatches(array $allowedMethods, string $method): bool
    {
        return in_array(strtoupper($method), array_map('strtoupper', $allowedMethods), true);
    }

    private function headersMatch(array $allowedHeaders, array $requiredHeaders): bool
    {
        foreach ($requiredHeaders as $requiredHeader) {
            $requiredHeader = strtolower($requiredHeader);
            $matched = false;

            foreach ($allowedHeaders as $allowedHeader) {
                $allowedHeader = strtolower($allowedHeader);
                if (
                    $allowedHeader === '*'
                    || $allowedHeader === $requiredHeader
                    || (str_ends_with($allowedHeader, '*') && str_starts_with($requiredHeader, rtrim($allowedHeader, '*')))
                ) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                return false;
            }
        }

        return true;
    }

    private function toStringList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        return is_array($value)
            ? array_values(array_filter($value, is_string(...)))
            : [];
    }
}
