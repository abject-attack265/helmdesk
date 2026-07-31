<?php

namespace App\Settings;

use App\Casts\NullableDataCast;
use App\Data\KnowledgeBase\KnowledgeEngineConfigData;
use App\Models\Attachment;
use App\Models\User;
use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $name = 'HelmDesk';

    public ?string $logo_id = null;

    public ?string $owner_id = null;

    public bool $registration_enabled = false;

    public ?KnowledgeEngineConfigData $knowledge_engine_config = null;

    public static function casts(): array
    {
        return [
            'knowledge_engine_config' => new NullableDataCast(KnowledgeEngineConfigData::class),
        ];
    }

    public static function group(): string
    {
        return 'general';
    }

    public function owner(): ?User
    {
        return filled($this->owner_id)
            ? User::withTrashed()->find($this->owner_id)
            : null;
    }

    public function logo(): ?Attachment
    {
        return filled($this->logo_id)
            ? Attachment::query()->find($this->logo_id)
            : null;
    }

    public function logoUrl(): string
    {
        return $this->logo()?->full_url ?? asset('images/logo-mark.png');
    }
}
