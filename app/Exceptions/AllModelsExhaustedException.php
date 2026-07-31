<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * 表示本轮所有模型候选均因可重试错误调用失败。
 */
class AllModelsExhaustedException extends RuntimeException
{
    /**
     * 保存按候选顺序排列的失败原因。
     *
     * @param  array<int, Throwable>  $candidateErrors  按候选顺序记录的各模型失败异常
     */
    public function __construct(
        string $message,
        public readonly array $candidateErrors = [],
    ) {
        parent::__construct($message);
    }
}
