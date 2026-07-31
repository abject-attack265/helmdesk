<?php

use App\Services\Search\CjkTokenizer;

return [
    'driver' => env('SCOUT_DRIVER', 'tntsearch'),
    'prefix' => '',
    'queue' => env('SCOUT_QUEUE', true) ? ['queue' => 'search-index'] : false,
    'after_commit' => false,
    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],
    'soft_delete' => false,
    'identify' => false,
    'tntsearch' => [
        'storage' => storage_path('scout'),
        'fuzziness' => false,
        'fuzzy' => [
            'prefix_length' => 2,
            'max_expansions' => 50,
            'distance' => 2,
            'no_limit' => true,
        ],
        'asYouType' => false,
        'searchBoolean' => false,
        'tokenizer' => CjkTokenizer::class,
    ],
];
