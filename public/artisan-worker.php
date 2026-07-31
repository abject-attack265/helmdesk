<?php

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Console\Signals;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

if (! defined('STDIN')) {
    define('STDIN', fopen('php://stdin', 'r'));
}
if (! defined('STDOUT')) {
    define('STDOUT', fopen('php://stdout', 'w'));
}
if (! defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'w'));
}

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Signals::resolveAvailabilityUsing(static fn (): bool => false);

$handler = static function (array $request): array {
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = $_SERVER['SCRIPT_FILENAME'] = 'artisan';
    $_SERVER['argv'] = ['artisan'];
    $_SERVER['argc'] = 1;

    $exit_code = Artisan::call($request['command'], $request['parameters']);

    return ['exit_code' => $exit_code, 'output' => Artisan::output()];
};

$maxRequests = (int) ($_SERVER['MAX_REQUESTS'] ?? 0);
for ($handled = 0; ! $maxRequests || $handled < $maxRequests; $handled++) {
    if (! frankenphp_handle_request($handler)) {
        break;
    }
    gc_collect_cycles();
}
