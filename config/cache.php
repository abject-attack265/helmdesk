<?php

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_STORE', 'database'),

    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => 'sqlite_cache',
            'table' => 'cache',
            'lock_connection' => 'sqlite_cache',
            'lock_table' => 'cache_locks',
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],
    ],

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),
];
