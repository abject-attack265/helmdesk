<?php

namespace App\Data\AiChat;

use App\Data\AiRuntime\RuntimeModelCandidateData;
use Spatie\LaravelData\Data;

/**
 * AI 助手单个模型候选成功完成后的流式消费结果。
 */
class AiChatStreamAttemptResultData extends Data
{
    /**
     * 保存实际使用的模型、回答内容、持久化片段和取消状态。
     *
     * @param  list<array<string, mixed>>  $segments
     */
    public function __construct(
        public RuntimeModelCandidateData $model_candidate,
        public string $content,
        public array $segments,
        public int $tool_call_count,
        public bool $cancelled,
    ) {}
}
