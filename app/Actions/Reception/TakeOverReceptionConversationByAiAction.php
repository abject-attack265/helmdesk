<?php

namespace App\Actions\Reception;

use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\HandoffReason;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Services\Reception\ChannelActivePlanVersionResolver;
use App\Services\Reception\ChannelAiAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 超时 AI 接管：把「排队无人接待超时」或「坐席无响应超时」的会话切回 AI 接待。
 *
 * 供两个入口复用：访客消息解析时的即时评估（FindOrCreateReceptionConversationAction，
 * 访客活跃时零延迟接管）与每分钟定时扫描（TakeOverOverdueReceptionConversationsAction，
 * 覆盖访客沉默场景）。是否到期由 ChannelAiAvailability 判定；本 Action 负责守卫
 * （渠道 AI 可用、非 AI 故障冷却期）、行级锁下的状态翻转与接待方案版本同步。
 */
class TakeOverReceptionConversationByAiAction
{
    use AsAction;

    /** AI 不可用后的冷却时长（秒），避免无人在线场景下循环切 AI → teammate_pending。 */
    public const int AI_COOLDOWN_SECONDS = 300;

    /**
     * 注入 AI 可用性判定与当前方案版本解析服务。
     */
    public function __construct(
        private readonly ChannelAiAvailability $aiAvailability,
        private readonly ChannelActivePlanVersionResolver $activePlanVersionResolver,
    ) {}

    /**
     * 评估并执行一次超时接管，返回是否发生了接管；未到期或守卫不满足时不处理。
     *
     * 接管时同步渠道当前部署版本，确保 AI 使用最新接待方案。
     */
    public function handle(Channel $channel, Conversation $conversation): bool
    {
        if (
            $conversation->status !== ConversationStatus::Open
            || ! $this->isOverdue($channel, $conversation)
            || ! $this->aiAvailability->canUseAi($channel)
            || $this->isAiCooldownActive($conversation)
        ) {
            return false;
        }

        $planVersionId = $this->activePlanVersionResolver->currentVersionForChannel($channel)?->id;

        /** @var array{previous_inbox_status: string, previous_assigned_user_id: ?string}|null $takeover */
        $takeover = DB::transaction(function () use ($conversation, $channel, $planVersionId): ?array {
            $locked = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->first();

            // 行锁建立后复核会话状态，避免覆盖同时发生的认领、回复或关单。
            if (
                ! $locked instanceof Conversation
                || $locked->status !== ConversationStatus::Open
                || ! $this->isOverdue($channel, $locked)
            ) {
                return null;
            }

            $previousInboxStatus = $locked->inbox_status->value;
            $previousAssignedUserId = $locked->assigned_user_id !== null
                ? (string) $locked->assigned_user_id
                : null;

            $locked->update([
                'assigned_user_id' => null,
                'reception_plan_version_id' => $planVersionId !== null ? (string) $planVersionId : null,
                'inbox_status' => ConversationInboxStatus::AiHandling,
            ]);

            return [
                'previous_inbox_status' => $previousInboxStatus,
                'previous_assigned_user_id' => $previousAssignedUserId,
            ];
        });

        if ($takeover === null) {
            return false;
        }

        $conversation->refresh();
        Log::info('[reception] 超时会话已由 AI 接管', [
            'channel_id' => (string) $channel->id,
            'conversation_id' => (string) $conversation->id,
            'previous_inbox_status' => $takeover['previous_inbox_status'],
            'previous_assigned_user_id' => $takeover['previous_assigned_user_id'],
            'reception_plan_version_id' => $conversation->reception_plan_version_id,
        ]);

        return true;
    }

    /**
     * 按会话当前形态判定对应的超时是否到期：排队会话看「无人接待超时」，已分配会话看「坐席无响应超时」。
     */
    private function isOverdue(Channel $channel, Conversation $conversation): bool
    {
        if (
            $conversation->inbox_status === ConversationInboxStatus::TeammatePending
            && $conversation->assigned_user_id === null
        ) {
            $queuedAt = $conversation->last_message_at ?? $conversation->created_at;

            return $this->aiAvailability->unassignedAiTakeoverIsDue($channel, $queuedAt);
        }

        if (
            $conversation->inbox_status === ConversationInboxStatus::TeammateHandling
            && $conversation->assigned_user_id !== null
        ) {
            return $this->aiAvailability->teammateNoResponseAiTakeoverIsDue($channel, $conversation);
        }

        return false;
    }

    /**
     * 判断会话是否处于 AI 不可用冷却期内，冷却期内不应自动切回 AI 接待。
     *
     * 通过查询最近一条 ai_unavailable 转人工事件的时间来判断，无需额外数据库字段。
     */
    private function isAiCooldownActive(Conversation $conversation): bool
    {
        $lastEvent = ConversationEvent::query()
            ->where('conversation_id', $conversation->id)
            ->where('type', ConversationEventType::HandoffRequested)
            ->where('payload->reason', HandoffReason::AiUnavailable)
            ->latest('created_at')
            ->value('created_at');

        if ($lastEvent === null) {
            return false;
        }

        return Carbon::parse($lastEvent)->addSeconds(self::AI_COOLDOWN_SECONDS)->isFuture();
    }
}
