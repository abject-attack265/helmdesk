<?php

namespace App\Services\Translation\Exceptions;

use Throwable;

/**
 * 表示翻译供应商的远端请求或响应失败。
 */
class TranslationProviderException extends TranslationException
{
    /**
     * 记录远端状态码、供应商标识和底层异常。
     *
     * @param  int|null  $statusCode  网络错误时为 null
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $providerSlug = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
