<?php

namespace App\Services\Reception;

use App\Actions\Reception\AppendAiMessageAction;
use App\Actions\Reception\RequestHandoffAction;
use App\Enums\HandoffReason;
use App\Exceptions\BusinessException;
use App\Models\Conversation;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

/**
 * 为单接待 agent 装配接待专属的 NeuronAI 工具：respond / handoff_to_human。
 * （knowledge_search 由共享的 KnowledgeSearchToolFactory 装配、集成工具由 IntegrationToolBuilder 装配，
 * 均在 RunReceptionTurnJob::buildToolset 处统一组装。）
 *
 * 单 agent 在一轮 ReAct 内同步完成检索与业务调用并直接回复访客：
 * - respond：循环过程中主动给访客发一条「过渡消息」（进度安抚 / 澄清提问），立即投递成独立气泡；
 *   本轮最终结论仍作为自然终止文本由 RunReceptionTurnJob 投递，不必经 respond。
 * - handoff_to_human：请求转人工。
 *
 * 每个工具回调通过闭包持有 conversationId 与所需服务，返回值（数组被 NeuronAI 序列化为 JSON）即工具结果文本。
 * 工具名、描述与返回语义保持稳定契约。
 */
class ReceptionToolFactory
{
    /**
     * 注入 AI 消息投递、抢占信号与转人工业务 Action。
     */
    public function __construct(
        private readonly AppendAiMessageAction $appendAiMessage,
        private readonly ReceptionPreemptionSignal $preemption,
        private readonly RequestHandoffAction $handoffAction,
    ) {}

    /**
     * 构造 respond 工具：在 ReAct 循环过程中主动给访客发一条过渡/补充消息，立即投递成独立气泡。
     *
     * 仅用于「还要继续调用工具、但想先对访客说点什么」（进度安抚、澄清提问、多气泡）；
     * 本轮最终结论应作为自然回复给出，由 RunReceptionTurnJob 投递，不经 respond。
     * 本轮被新访客消息抢占、或会话已不在 AI 接待态时跳过投递并回告 agent。
     */
    public function buildRespondTool(
        Conversation $conversation,
        string $conversationId,
        string $turnId,
        ReceptionTurnDelivery $delivery,
        ?string $receptionPlanVersionId = null,
    ): Tool {
        return $this->respondToolSpec()
            ->setCallable(function (?string $message) use ($conversation, $conversationId, $turnId, $delivery, $receptionPlanVersionId): array {
                $text = trim((string) $message);
                if ($text === '') {
                    return ['error' => 'message_required'];
                }

                // 本轮已被新访客消息抢占：停止继续投递气泡，避免对过期上下文喋喋不休。
                if ($this->preemption->isPreempted($conversationId, $turnId)) {
                    return ['error' => 'preempted'];
                }

                try {
                    // 盖上 turn_id：respond 过渡气泡也归属本轮，供「复制消息 ID → 查调用日志」与调用日志详情按轮叠加工具。
                    $this->appendAiMessage->handle($conversation, $text, null, $receptionPlanVersionId, $turnId);
                } catch (BusinessException) {
                    // 会话已转人工 / 被接管，无法继续向访客投递。
                    return ['error' => 'not_deliverable'];
                }

                $delivery->markDelivered();

                return ['ok' => true];
            });
    }

    /**
     * 构造 respond 工具的名称、描述与入参。
     */
    private function respondToolSpec(): Tool
    {
        return Tool::make(
            'respond',
            '在你还要继续调用工具时，先给访客发送一条过渡消息（如进度安抚「好的，我帮您查一下，请稍候」或澄清提问），消息会立即作为独立气泡送达访客。不要用它发送本轮最终结论——最终结论直接作为你的回复给出即可。',
        )
            ->addProperty(new ToolProperty('message', PropertyType::STRING, '要发送给访客的消息内容，必须使用访客语言，不得包含任何内部工具名或系统术语。', true));
    }

    /**
     * 构造 handoff_to_human 工具：请求把会话从 AI 接待切到人工坐席。
     *
     * 工具按营业时间与人工可用状态返回 accepted，并由 RequestHandoffAction 直接把 notice 送达访客；
     * quotedMessageId 来自当前轮最后一条访客消息（渠道开启引用时），用于把转人工提示引用到访客消息上。
     * 可选字段（business_hours_summary / next_available_at）为空时省略。
     */
    public function buildHandoffTool(Conversation $conversation, ?string $quotedMessageId = null): Tool
    {
        return $this->handoffToolSpec()
            ->setCallable(function (?string $reason, ?string $summary) use ($conversation, $quotedMessageId): array {
                // reason 由 RequestHandoffAction 入口统一归一化，未知/空值会落到 ai_requested。
                $decision = $this->handoffAction->handle(
                    conversation: $conversation,
                    reason: (string) $reason,
                    quotedMessageId: $quotedMessageId,
                    summary: $summary !== null && trim($summary) !== '' ? trim($summary) : null,
                );

                $result = [
                    'accepted' => $decision->accepted,
                    'reason' => $decision->reason,
                    'notice' => $decision->notice,
                    'human_available' => $decision->human_available,
                ];
                if ($decision->business_hours_summary !== null) {
                    $result['business_hours_summary'] = $decision->business_hours_summary;
                }
                if ($decision->next_available_at !== null) {
                    $result['next_available_at'] = $decision->next_available_at;
                }

                return $result;
            });
    }

    /**
     * 构造 handoff_to_human 工具的名称、描述与入参。
     */
    private function handoffToolSpec(): Tool
    {
        return Tool::make(
            'handoff_to_human',
            '请求把会话从 AI 接待切到人工坐席；工具会按营业时间和人工可用状态返回 accepted，并直接把 notice 送达访客。',
        )
            ->addProperty(new ToolProperty('reason', PropertyType::STRING, '转人工原因，须从以下取值中选择其一："'.implode('"、"', HandoffReason::aiSelectableValues()).'"；无法判断时用 ai_requested。', true))
            ->addProperty(new ToolProperty('summary', PropertyType::STRING, '给坐席看的会话上下文摘要，方便他直接接手；必须使用访客语言。', true));
    }
}
