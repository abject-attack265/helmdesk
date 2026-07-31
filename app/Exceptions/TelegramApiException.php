<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Telegram Bot API 调用失败异常。
 *
 * 携带 Telegram 返回的状态码与描述：上层据此把失败转换为对应的 BusinessException 文案，
 * 或回写消息投递状态。
 */
class TelegramApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
