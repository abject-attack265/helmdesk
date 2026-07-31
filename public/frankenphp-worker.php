<?php

// Set a default for the application base path and public path if they are missing...
$_SERVER['APP_BASE_PATH'] = $_ENV['APP_BASE_PATH'] ?? $_SERVER['APP_BASE_PATH'] ?? __DIR__.'/..';
$_SERVER['APP_PUBLIC_PATH'] = $_ENV['APP_PUBLIC_PATH'] ?? $_SERVER['APP_PUBLIC_PATH'] ?? __DIR__;

if (! defined('STDIN')) {
    define('STDIN', fopen('php://stdin', 'r'));
}
if (! defined('STDOUT')) {
    define('STDOUT', fopen('php://stdout', 'w'));
}
if (! defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'w'));
}

require __DIR__.'/../vendor/laravel/octane/bin/frankenphp-worker.php';
