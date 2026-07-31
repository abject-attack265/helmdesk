<?php

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Console\Signals;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Queue\WorkerOptions;

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
    $queue = $request['queue'];
    $connection = app('queue')->connection('database');
    $job = $connection->pop($queue);
    if ($job === null) {
        return ['processed' => false];
    }

    try {
        app('queue.worker')->process('database', $job, new WorkerOptions(
            memory: 256,
            timeout: 1200,
            sleep: 1,
            maxTries: 1,
        ));
    } catch (Throwable $exception) {
        report($exception);

        return ['processed' => true, 'failed' => true, 'job' => $job->getName()];
    }

    return ['processed' => true, 'job' => $job->getName()];
};

$maxRequests = (int) ($_SERVER['MAX_REQUESTS'] ?? 0);
for ($handled = 0; ! $maxRequests || $handled < $maxRequests; $handled++) {
    if (! frankenphp_handle_request($handler)) {
        break;
    }
    gc_collect_cycles();
}
