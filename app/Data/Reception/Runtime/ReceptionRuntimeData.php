<?php

namespace App\Data\Reception\Runtime;

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Data\Integration\IntegrationToolSourceRuntimeData;
use App\Data\Reception\Plan\CompiledKnowledgeBaseData;
use App\Enums\ReceptionRuntimeUnavailableReason;
use Spatie\LaravelData\Data;

/**
 * 一次接待 turn 的运行时配置。
 * 由 LoadReceptionRuntimeAction 按会话锁定的方案版本与全局模型池组装，RunReceptionTurnJob 消费：
 * available=false 时仅携带 reason，调用方停止本会话的 AI 接待；available=true 时携带完整推理配置。
 *
 * 单 agent 架构：knowledge_search 与集成工具直接挂在接待 agent 上，运行时一并下发可检索知识库快照与集成工具来源。
 */
class ReceptionRuntimeData extends Data
{
    /**
     * @param  list<RuntimeModelCandidateData>  $model_candidates  接待用途的模型候选（按优先级升序）
     * @param  list<CompiledKnowledgeBaseData>  $knowledge_bases  可检索的知识库快照
     * @param  list<IntegrationToolSourceRuntimeData>  $integration_tool_sources  可挂载的集成工具来源规格
     * @param  ?string  $contact_external_id  当前会话联系人的外部 ID（业务系统工具调用上下文，无则 null）
     * @param  ?string  $conversation_id  当前会话 id（业务系统工具调用上下文）
     * @param  ?string  $contact_email  当前会话联系人主邮箱（业务系统工具调用上下文，无则 null）
     * @param  ?string  $reception_plan_version_id  本轮解析到的、当前驱动会话的接待方案版本快照 id（盖到本轮 AI 消息上做逐消息审计）
     */
    public function __construct(
        public bool $available,
        public ?ReceptionRuntimeUnavailableReason $reason = null,
        public string $system_prompt = '',
        public array $model_candidates = [],
        public array $knowledge_bases = [],
        public array $integration_tool_sources = [],
        public string $ai_unavailable_notice = '',
        public bool $quote_visitor_message_enabled = false,
        public ?string $contact_external_id = null,
        public ?string $conversation_id = null,
        public ?string $contact_email = null,
        public ?string $reception_plan_version_id = null,
    ) {}

    /**
     * 构造一份"运行时不可用"的配置。
     */
    public static function unavailable(ReceptionRuntimeUnavailableReason $reason): self
    {
        return new self(available: false, reason: $reason);
    }

    /**
     * 本会话允许检索的知识库 ID 列表，下推给 knowledge_search 工具做范围限定。
     *
     * @return list<string>
     */
    public function knowledgeBaseIds(): array
    {
        return array_map(
            static fn (CompiledKnowledgeBaseData $kb): string => $kb->id,
            $this->knowledge_bases,
        );
    }
}
