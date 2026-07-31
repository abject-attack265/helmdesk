<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.name', 'HelmDesk');
        $this->migrator->add('general.logo_id', null);
        $this->migrator->add('general.owner_id', null);
        $this->migrator->add('general.registration_enabled', false);
        $this->migrator->add('general.knowledge_engine_config', null);
    }

    public function down(): void
    {
        $this->migrator->delete('general.name');
        $this->migrator->delete('general.logo_id');
        $this->migrator->delete('general.owner_id');
        $this->migrator->delete('general.registration_enabled');
        $this->migrator->delete('general.knowledge_engine_config');
    }
};
