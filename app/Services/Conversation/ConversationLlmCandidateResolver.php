<?php

namespace App\Services\Conversation;

use App\Enums\AiModelPurpose;
use App\Models\AiModel;
use App\Models\Conversation;
use App\Services\AiRuntime\AiModelPool;

/**
 * 解析会话级轻量 AI 任务（摘要 / 标签 / 主题等）可用的 LLM 候选模型。
 *
 * 统一从全局 background_task 用途池取用，随机打散、失败轮询。
 */
class ConversationLlmCandidateResolver
{
    public function __construct(
        private readonly AiModelPool $pool,
    ) {}

    /**
     * 返回 background_task 用途下打散后的候选模型列表（含 provider 关联）。
     *
     * @return list<AiModel>
     */
    public function resolve(Conversation $conversation): array
    {
        return $this->pool->modelsForPurpose(AiModelPurpose::BackgroundTask)->all();
    }
}
