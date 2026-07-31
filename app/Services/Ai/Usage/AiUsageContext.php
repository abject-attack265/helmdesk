<?php

namespace App\Services\Ai\Usage;

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Enums\AiCallPurpose;
use App\Enums\AiModelPurpose;
use App\Models\AiModel;
use App\Services\Ai\Logging\CallLoggingObserver;
use NeuronAI\Agent\Agent;

/**
 * 一次 LLM 调用的归因上下文。
 *
 * 背景生成、接待和助手流式调用在此封装模型与会话信息，
 * 供用量日志和完整调用日志共用。
 *
 * 前半段字段是用量归因（谁的、哪个池、哪个会话）；call_* 字段是调用日志的业务维度与定位钥匙
 * （这次调用是为做什么、能从哪条 turn / 消息 / 联系人反查到它），由调用日志观测器使用。
 */
final class AiUsageContext
{
    public function __construct(
        // 模型池用途（写 ai_usage_logs）。没有池用途的调用仅记调用日志、不记用量。
        public readonly ?AiModelPurpose $purpose,
        public readonly ?string $aiModelId = null,
        public readonly string $modelName = '',
        public readonly ?string $conversationId = null,
        public readonly ?AiCallPurpose $callPurpose = null,
        public readonly ?string $turnId = null,
        public readonly ?string $conversationMessageId = null,
        public readonly ?string $contactId = null,
        /** 本轮输入对应的会话消息 ID 列表（接待场景，调用日志用于回链消息与附件） @var list<string> */
        public readonly array $conversationMessageIds = [],
    ) {}

    /**
     * 从已取得的 AiModel 构造（背景生成类调用：摘要 / 标签 / 润色等）。
     */
    public static function forModel(
        AiModel $model,
        ?string $conversationId = null,
        ?AiCallPurpose $callPurpose = null,
        ?string $conversationMessageId = null,
        ?string $contactId = null,
    ): self {
        return new self(
            purpose: $model->purpose,
            aiModelId: $model->id,
            modelName: (string) $model->name,
            conversationId: $conversationId,
            callPurpose: $callPurpose,
            conversationMessageId: $conversationMessageId,
            contactId: $contactId,
        );
    }

    /**
     * 从运行时模型候选构造（接待 / 助手流式调用：手头只有候选而非 AiModel）。
     */
    public static function forCandidate(
        RuntimeModelCandidateData $candidate,
        AiModelPurpose $purpose,
        ?string $conversationId = null,
        ?AiCallPurpose $callPurpose = null,
        ?string $turnId = null,
        array $conversationMessageIds = [],
        ?string $contactId = null,
    ): self {
        return new self(
            purpose: $purpose,
            aiModelId: $candidate->ai_model_id !== '' ? $candidate->ai_model_id : null,
            modelName: $candidate->model_name,
            conversationId: $conversationId,
            callPurpose: $callPurpose,
            turnId: $turnId,
            contactId: $contactId,
            conversationMessageIds: $conversationMessageIds,
        );
    }

    /**
     * 给 Agent 挂载 token 用量和完整调用日志观测器。
     */
    public function attachObservers(Agent $agent): void
    {
        $agent->observe(new UsageRecordingObserver($this));
        $agent->observe(new CallLoggingObserver($this, $agent));
    }
}
