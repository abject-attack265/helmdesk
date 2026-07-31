<?php

namespace App\Data\System;

use Spatie\LaravelData\Data;

class FormUpdateSystemSettingsData extends Data
{
    public function __construct(
        public string $name,
        public ?string $logo_id = null,
        public bool $registration_enabled = false,
    ) {}
}
