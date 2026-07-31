<?php

namespace App\Services\AiRuntime;

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Exceptions\AllModelsExhaustedException;
use App\Exceptions\ModelAttemptTimeoutException;
use Throwable;

/**
 * 按候选顺序调用模型，并在可重试错误后尝试下一个候选。
 *
 * 认证、限流、服务端、模型不存在、单次推理超时与多模态拒收错误允许切换；
 * 请求格式、上下文超长和取消等错误直接抛出。
 */
class AiModelFallback
{
    /**
     * 依次尝试候选模型，返回首个成功的执行结果。
     *
     * @param  list<RuntimeModelCandidateData>  $candidates  运行时下发的模型候选（按优先级升序）
     * @param  callable(RuntimeModelCandidateData, int): mixed  $attempt  对单个候选执行一次推理；成功返回结果，失败抛异常
     *
     * @throws AllModelsExhaustedException 全部候选耗尽
     * @throws Throwable 遇到不可重试错误时原样抛出
     */
    public function run(array $candidates, callable $attempt): mixed
    {
        $candidates = array_values($candidates);
        if ($candidates === []) {
            throw new AllModelsExhaustedException('运行时没有可用的模型候选。');
        }

        $errors = [];
        foreach ($candidates as $index => $candidate) {
            try {
                return $attempt($candidate, $index);
            } catch (Throwable $error) {
                $errors[$index] = $error;
                if (! $this->isRetryable($error)) {
                    throw $error;
                }
            }
        }

        throw new AllModelsExhaustedException('所有候选模型均调用失败。', $errors);
    }

    /**
     * 判断上游模型错误是否允许切换候选。
     */
    public function isRetryable(Throwable $error): bool
    {
        if ($error instanceof ModelAttemptTimeoutException) {
            return true;
        }

        $message = strtolower($error->getMessage());

        return $this->containsAny($message, [
            '401', '403', 'unauthorized', 'forbidden', 'authentication',
            'invalid api key', 'incorrect api key', 'invalid x-api-key', 'permission denied',
            '429', 'rate limit', 'rate_limit', 'too many requests',
            'quota exceeded', 'quota_exceeded', 'insufficient_quota', 'billing',
            '500', '502', '503', 'internal server error', 'bad gateway',
            'service unavailable', 'service_unavailable', 'server_error', 'overloaded',
            'model not found', 'model_not_found', 'model does not exist',
            'no such model', 'invalid model', 'model is not available',
            'multimodal', 'corrupted', 'cannot be processed', 'fetch url', 'failed to fetch',
        ]);
    }

    /**
     * 检查字符串是否包含任一错误特征。
     *
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
