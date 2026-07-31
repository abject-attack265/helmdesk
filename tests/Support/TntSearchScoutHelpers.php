<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

function bootTntSearch(): void
{
    $storage = storage_path('framework/testing/scout-'.Str::random(8));

    File::ensureDirectoryExists($storage);
    config()->set('scout.driver', 'tntsearch');
    config()->set('scout.prefix', '');
    config()->set('scout.queue', false);
    config()->set('scout.tntsearch.storage', $storage);
    config()->set('scout.tntsearch.fuzziness', false);
    config()->set('scout.tntsearch.searchBoolean', false);
}

function flushTntSearch(): void
{
    File::deleteDirectory((string) config('scout.tntsearch.storage'));
}
