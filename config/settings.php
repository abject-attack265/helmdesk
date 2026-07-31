<?php

use App\Settings\GeneralSettings;
use App\Settings\StorageSettings;
use Spatie\LaravelData\Data;
use Spatie\LaravelSettings\SettingsCasts\DataCast;
use Spatie\LaravelSettings\SettingsCasts\DateTimeInterfaceCast;
use Spatie\LaravelSettings\SettingsCasts\DateTimeZoneCast;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

return [
    'settings' => [
        GeneralSettings::class,
        StorageSettings::class,
    ],

    'setting_class_path' => app_path('Settings'),

    'migrations_paths' => [
        database_path('settings'),
    ],

    'default_repository' => 'database',

    'repositories' => [
        'database' => [
            'type' => DatabaseSettingsRepository::class,
            'model' => null,
            'table' => 'settings',
            'connection' => 'sqlite',
        ],
    ],

    'encoder' => null,
    'decoder' => null,

    'cache' => [
        'enabled' => env('SETTINGS_CACHE_ENABLED', false),
        'store' => null,
        'prefix' => null,
        'ttl' => null,
    ],

    'global_casts' => [
        DateTimeInterface::class => DateTimeInterfaceCast::class,
        DateTimeZone::class => DateTimeZoneCast::class,
        Data::class => DataCast::class,
    ],

    'auto_discover_settings' => [
        app_path('Settings'),
    ],

    'discovered_settings_cache_path' => base_path('bootstrap/cache'),
];
