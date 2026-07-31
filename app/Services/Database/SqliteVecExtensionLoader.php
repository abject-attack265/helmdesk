<?php

namespace App\Services\Database;

use Illuminate\Database\Connection;
use RuntimeException;

class SqliteVecExtensionLoader
{
    public function ensureLoadedFor(Connection $connection): void
    {
        if ($connection->getName() !== 'sqlite_rag') {
            return;
        }

        $path = config('sqlite_vec.path');
        if (! is_string($path)) {
            throw new RuntimeException(sprintf(
                'sqlite-vec is not available for %s/%s.',
                PHP_OS_FAMILY,
                php_uname('m'),
            ));
        }

        $connection->getPdo()->loadExtension($path);
    }
}
