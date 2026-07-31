<?php

namespace App\Data\StorageSetting;

use Spatie\LaravelData\Data;

class FormUpdateStorageProfileData extends Data
{
    public function __construct(
        public string $name,
        public ?string $url = null,
        public ?string $key = null,
        public ?string $secret = null,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'url' => ['nullable', 'string', 'url'],
            'key' => ['nullable', 'string'],
            'secret' => ['nullable', 'string'],
        ];
    }
}
