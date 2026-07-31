<?php

namespace App\Services\Reception;

use App\Enums\AiModelPurpose;
use App\Enums\ConversationInboxStatus;
use App\Enums\MessageRole;
use App\Enums\ReceptionRoutingMode;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\AiRuntime\AiModelPool;
use Carbon\CarbonInterface;

/**
 * 判断网站渠道当前是否可以交给 AI 接待，以及各类 AI 接管时机。
 *
 * 接待是否可用，等价于渠道当前部署的 PlanVersion 处于已发布状态，且全局存在可用的接待用途模型。
 */
class ChannelAiAvailability
{
    /**
     * 注入模型用途池与版本解析器以判断方案最新版与全局接待模型是否可用。
     */
    public function __construct(
        private readonly AiModelPool $aiModelPool,
        private readonly ReceptionPlanStrategyResolver $strategyResolver,
        private readonly ChannelActivePlanVersionResolver $activePlanVersionResolver,
    ) {}

    /**
     * 判断渠道绑定方案的最新已发布版本足以运行 AI 接待。
     */
    public function canUseAi(Channel $channel): bool
    {
        if (! filled($channel->reception_plan_id)) {
            return false;
        }

        $version = $this->activePlanVersionResolver->currentVersionForChannel($channel);

        if ($version === null) {
            return false;
        }

        return $this->aiModelPool->hasUsable(AiModelPurpose::ReceptionChat);
    }

    /**
     * 判断人工接待会话是否已经超过待接待等待时间，应转给 AI 接待。
     */
    public function unassignedAiTakeoverIsDue(Channel $channel, ?CarbonInterface $queuedAt): bool
    {
        $strategy = $this->strategyResolver->forChannel($channel);

        if (
            $strategy->reception_mode !== ReceptionRoutingMode::TeammateFirst
            || ! $strategy->unassigned_ai_takeover_enabled
            || $queuedAt === null
        ) {
            return false;
        }

        return $queuedAt->copy()
            ->addSeconds($strategy->unassigned_ai_takeover_timeout_seconds)
            ->lte(now());
    }

    /**
     * 判断客服已接待会话是否因客服无响应而应转给 AI 接待。
     */
    public function teammateNoResponseAiTakeoverIsDue(Channel $channel, Conversation $conversation): bool
    {
        $strategy = $this->strategyResolver->forChannel($channel);

        if (
            ! $strategy->teammate_no_response_ai_takeover_enabled
            || $conversation->assigned_user_id === null
            || $conversation->inbox_status !== ConversationInboxStatus::TeammateHandling
        ) {
            return false;
        }

        $lastMessage = ConversationMessage::query()

            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($lastMessage?->role !== MessageRole::Visitor || $lastMessage->created_at === null) {
            return false;
        }

        return $lastMessage->created_at->copy()
            ->addSeconds($strategy->teammate_no_response_ai_takeover_timeout_seconds)
            ->lte(now());
    }
}
