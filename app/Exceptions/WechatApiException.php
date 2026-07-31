<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/** 表达微信公众号 API 返回的业务错误与重试属性。 */
class WechatApiException extends RuntimeException
{
    /** 创建微信公众号 API 异常。 */
    public function __construct(
        string $message,
        public readonly int $errorCode = 0,
        ?Throwable $previous = null,
        private readonly bool $retryable = false,
    ) {
        parent::__construct($message, $errorCode, $previous);
    }

    /** 从微信公众号响应构建带错误码的异常。 */
    public static function fromResult(array $result, string $fallbackMessage): self
    {
        $errorCode = (int) ($result['errcode'] ?? 0);
        $message = (string) ($result['errmsg'] ?? $fallbackMessage);

        return new self(
            $message,
            $errorCode,
            retryable: self::isRetryableErrorCode($errorCode),
        );
    }

    /** 判断当前错误是否适合队列重试。 */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /** 判断微信错误码是否表示临时故障。 */
    private static function isRetryableErrorCode(int $errorCode): bool
    {
        return in_array($errorCode, [-1, 40001, 40014, 42001, 45009, 45011], true);
    }
}
