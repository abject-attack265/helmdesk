<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('storage.enabled', false);
        $this->migrator->add('storage.current_profile_id', null);
    }

    public function down(): void
    {
        $this->migrator->delete('storage.enabled');
        $this->migrator->delete('storage.current_profile_id');
    }
};
