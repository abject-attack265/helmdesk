<?php

namespace App\Data\System;

use App\Settings\GeneralSettings;
use Spatie\LaravelData\Data;

class SystemData extends Data
{
    public function __construct(
        public string $name,
        public ?string $logo_id,
        public string $logo_url,
        public bool $registration_enabled,
    ) {}

    public static function fromSettings(GeneralSettings $settings): self
    {
        return new self(
            name: $settings->name,
            logo_id: $settings->logo_id,
            logo_url: $settings->logoUrl(),
            registration_enabled: $settings->registration_enabled,
        );
    }
}
