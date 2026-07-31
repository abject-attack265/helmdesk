<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 表示单个候选模型在流式消费期间超过本次尝试的时限。
 */
class ModelAttemptTimeoutException extends RuntimeException
{
    /**
     * @param  float  $elapsed_seconds  本次推理实际耗时（秒）
     * @param  float  $timeout_seconds  触发中断的 per-attempt 时限（秒）
     */
    public function __construct(
        public readonly float $elapsed_seconds,
        public readonly float $timeout_seconds,
    ) {
        parent::__construct(sprintf(
            '单个候选模型推理超时：已耗时 %.1fs，超过 per-attempt 时限 %.1fs。',
            $elapsed_seconds,
            $timeout_seconds,
        ));
    }
}
