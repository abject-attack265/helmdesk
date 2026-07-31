<?php

namespace App\Casts;

use Spatie\LaravelData\Data;
use Spatie\LaravelSettings\SettingsCasts\SettingsCast;

class NullableDataCast implements SettingsCast
{
    public function __construct(
        private readonly string $dataClass,
    ) {}

    public function get($payload): ?Data
    {
        return $payload === null ? null : $this->dataClass::from($payload);
    }

    public function set($payload): ?array
    {
        return $payload === null ? null : $payload->toArray();
    }
}
