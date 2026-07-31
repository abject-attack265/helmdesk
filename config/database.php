<?php

return [
    'default' => env('DB_CONNECTION', 'sqlite'),

    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', storage_path('database/main.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
            'transaction_mode' => 'immediate',
        ],

        'sqlite_rag' => [
            'driver' => 'sqlite',
            'database' => env('DB_RAG_DATABASE', storage_path('database/rag.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
            'transaction_mode' => 'immediate',
        ],

        'sqlite_cache' => [
            'driver' => 'sqlite',
            'database' => env('DB_CACHE_DATABASE', storage_path('database/cache.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
            'transaction_mode' => 'immediate',
        ],

        'sqlite_session' => [
            'driver' => 'sqlite',
            'database' => env('DB_SESSION_DATABASE', storage_path('database/session.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
            'transaction_mode' => 'immediate',
        ],

        'sqlite_jobs' => [
            'driver' => 'sqlite',
            'database' => env('DB_JOBS_DATABASE', storage_path('database/jobs.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 60000,
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
            'transaction_mode' => 'immediate',
        ],
    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
];
