<?php

return [
    'default' => env('QUEUE_CONNECTION', 'database'),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => 'sqlite_jobs',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 1200,
            'after_commit' => false,
        ],
    ],

    'batching' => [
        'database' => 'sqlite_jobs',
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => 'sqlite_jobs',
        'table' => 'failed_jobs',
    ],
];
