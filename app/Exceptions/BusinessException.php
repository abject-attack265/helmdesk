<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * 业务逻辑异常
 */
class BusinessException extends RuntimeException
{
    public function __construct(string $message, int $code = 200, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
