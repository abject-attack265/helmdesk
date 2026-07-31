<?php

namespace App\Services\Ai\Usage;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Log;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Observability\Events\InferenceStop;
use NeuronAI\Observability\ObserverInterface;
use Throwable;

/**
 * NeuronAI 用量观测器：在每次推理结束（inference-stop）时把 token 用量落库。
 *
 * 挂到 Agent 上后按 AiUsageContext 归因写入 ai_usage_logs。
 * chat、structured 和 stream 模式都会触发 inference-stop；一次接待轮次内的多次模型调用逐行累计。
 *
 * 用量计量是旁路观测：写入失败仅记日志，绝不阻断 LLM 正常服务。
 */
final class UsageRecordingObserver implements ObserverInterface
{
    public function __construct(
        private readonly AiUsageContext $context,
    ) {}

    /**
     * 处理 NeuronAI 事件；仅消费 inference-stop，从响应消息提取 token 用量并落库。
     */
    public function onEvent(string $event, object $source, mixed $data = null, ?string $branchId = null): void
    {
        if ($event !== 'inference-stop' || ! $data instanceof InferenceStop) {
            return;
        }

        // 无池用途的调用只记调用日志、不记用量，避免给 ai_usage_logs 造无 purpose 的脏行。
        if ($this->context->purpose === null) {
            return;
        }

        $usage = $data->response->getUsage();
        if (! $usage instanceof Usage) {
            return;
        }

        try {
            AiUsageLog::query()->create([
                'ai_model_id' => $this->context->aiModelId,
                'model_name' => $this->context->modelName,
                'purpose' => $this->context->purpose,
                'conversation_id' => $this->context->conversationId,
                'input_tokens' => max(0, $usage->inputTokens),
                'output_tokens' => max(0, $usage->outputTokens),
            ]);
        } catch (Throwable $exception) {
            Log::warning('AI 用量记录写入失败（best-effort，不阻断推理）', [
                'purpose' => $this->context->purpose->value,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
