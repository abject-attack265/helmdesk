<?php

namespace App\Services\Realtime;

use App\Events\Reception\InstanceReceptionUpdated;
use App\Events\Reception\ReceptionAgentActivityUpdated;
use App\Events\Reception\VisitorConversationUpdated;
use App\Models\Conversation;
use App\Services\Reception\ReceptionActivityRegistry;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * 发送接待会话的实时变更与接待方活动状态。
 *
 * 会话变更只携带刷新信号和列表元信息，客户端收到后回源读取完整状态。
 * 广播 I/O 失败记录 warning；完整会话缺少线程时直接失败。
 */
class ReceptionRealtimeNotifier
{
    /** 人工客服页面活动租约时长，覆盖两次正常续期间隔。 */
    private const int TEAMMATE_ACTIVITY_HOLD_MILLISECONDS = 8000;

    /** AI debounce 活动租约时长，与缓冲状态生命周期一致。 */
    private const int AI_DEBOUNCE_ACTIVITY_HOLD_MILLISECONDS = 600000;

    /** AI turn 排队活动覆盖 WithoutOverlapping 的重试窗口。 */
    private const int AI_QUEUED_ACTIVITY_HOLD_MILLISECONDS = 330000;

    /** AI turn 执行活动覆盖单个任务硬超时及清理余量。 */
    private const int AI_RUNNING_ACTIVITY_HOLD_MILLISECONDS = 210000;

    /** AI debounce 在单会话主题内使用稳定来源 ID。 */
    private const string AI_DEBOUNCE_ACTIVITY_ID = 'ai:debounce';

    /**
     * 注入接待方活动租约注册表。
     */
    public function __construct(
        private readonly ReceptionActivityRegistry $activityRegistry,
    ) {}

    /**
     * 通知收件箱和访客端会话已变更。
     *
     * @param  array<string, mixed>  $meta
     */
    public function conversationChanged(Conversation $conversation, string $event, array $meta = []): void
    {
        $conversation = Conversation::query()
            ->leftJoin('conversation_threads as resolved_thread', function (JoinClause $join): void {
                $join
                    ->on('resolved_thread.contact_id', '=', 'conversations.contact_id')
                    ->on('resolved_thread.channel_id', '=', 'conversations.channel_id');
            })
            ->select('conversations.*', 'resolved_thread.id as resolved_thread_id')
            ->where('conversations.id', $conversation->id)
            ->firstOrFail();
        /** @var string|null $threadId */
        $threadId = $conversation->getAttribute('resolved_thread_id');
        $conversation->loadMissing(['channel', 'contact']);

        if (
            $threadId === null
            && $conversation->contact_id !== null
            && $conversation->channel_id !== null
        ) {
            Log::warning('[reception] 完整会话缺少收件箱线程', [
                'event' => $event,
                'conversation_id' => (string) $conversation->id,
                'contact_id' => (string) $conversation->contact_id,
                'channel_id' => (string) $conversation->channel_id,
            ]);

            throw new LogicException("完整会话 {$conversation->id} 缺少收件箱线程。");
        }

        $occurredAt = now()->toIso8601String();

        $this->dispatchBestEffort(new InstanceReceptionUpdated([
            'event' => $event,
            'conversation_id' => (string) $conversation->id,
            'thread_id' => $threadId,
            'contact_id' => $conversation->contact_id !== null ? (string) $conversation->contact_id : null,
            'occurred_at' => $occurredAt,
            'assigned_user_id' => $conversation->assigned_user_id !== null ? (string) $conversation->assigned_user_id : null,
            'status' => $conversation->status->value,
            'inbox_status' => $conversation->inbox_status->value,
            'last_message_preview' => $conversation->last_message_preview,
            'contact_name' => $conversation->contact?->name,
            'channel_name' => $conversation->channel?->name,
            ...$meta,
        ]));

        $this->dispatchBestEffort(new VisitorConversationUpdated((string) $conversation->id, [
            'event' => $event,
            'conversation_id' => (string) $conversation->id,
            'occurred_at' => $occurredAt,
        ]));
    }

    /**
     * 续期或释放人工客服页面的会话活动租约。
     */
    public function teammateActivity(Conversation $conversation, string $activityId, int $sequence, bool $active): void
    {
        $conversationId = (string) $conversation->id;
        $resolvedActivityId = 'teammate:'.$activityId;
        $accepted = $active
            ? $this->activityRegistry->renewOrdered(
                $conversationId,
                $resolvedActivityId,
                self::TEAMMATE_ACTIVITY_HOLD_MILLISECONDS,
                $sequence,
            )
            : $this->activityRegistry->releaseOrdered($conversationId, $resolvedActivityId, $sequence);
        if (! $accepted) {
            return;
        }

        $this->dispatchActivityState($conversationId);
    }

    /**
     * 建立或续期 AI debounce 活动租约。
     */
    public function aiDebounceStarted(string $conversationId): void
    {
        $this->activityRegistry->renew(
            $conversationId,
            self::AI_DEBOUNCE_ACTIVITY_ID,
            self::AI_DEBOUNCE_ACTIVITY_HOLD_MILLISECONDS,
        );

        $this->dispatchActivityState($conversationId);
    }

    /**
     * 释放 AI debounce 活动租约。
     */
    public function aiDebounceStopped(string $conversationId): void
    {
        $this->activityRegistry->release($conversationId, self::AI_DEBOUNCE_ACTIVITY_ID);

        $this->dispatchActivityState($conversationId);
    }

    /**
     * 在 AI turn 进入队列时建立活动租约。
     */
    public function aiTurnQueued(string $conversationId, string $activityId): void
    {
        $resolvedActivityId = 'ai:turn:'.$activityId;
        $this->activityRegistry->renew(
            $conversationId,
            $resolvedActivityId,
            self::AI_QUEUED_ACTIVITY_HOLD_MILLISECONDS,
        );

        $this->dispatchActivityState($conversationId);
    }

    /**
     * 在 AI turn 开始执行时续期活动租约。
     */
    public function aiTurnStarted(string $conversationId, string $activityId): void
    {
        $resolvedActivityId = 'ai:turn:'.$activityId;
        $this->activityRegistry->renew(
            $conversationId,
            $resolvedActivityId,
            self::AI_RUNNING_ACTIVITY_HOLD_MILLISECONDS,
        );

        $this->dispatchActivityState($conversationId);
    }

    /**
     * 在 AI turn 结束时释放对应活动租约。
     */
    public function aiTurnStopped(string $conversationId, string $activityId): void
    {
        $resolvedActivityId = 'ai:turn:'.$activityId;
        $this->activityRegistry->release($conversationId, $resolvedActivityId);

        $this->dispatchActivityState($conversationId);
    }

    /**
     * 向后台实例主题发布轻量变更信号。
     *
     * @param  array<string, mixed>  $meta
     */
    public function appChanged(string $event, array $meta = []): void
    {
        $this->dispatchBestEffort(new InstanceReceptionUpdated([
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            ...$meta,
        ]));
    }

    /**
     * 广播会话所有活动来源的聚合状态。
     */
    private function dispatchActivityState(string $conversationId): void
    {
        $state = $this->activityRegistry->current($conversationId);

        $this->dispatchBestEffort(new ReceptionAgentActivityUpdated(
            conversationId: $conversationId,
            active: $state->active,
            holdMilliseconds: $state->hold_ms,
            revision: $state->revision,
        ));
    }

    /**
     * 发布事件并记录发布异常。
     */
    private function dispatchBestEffort(InstanceReceptionUpdated|VisitorConversationUpdated|ReceptionAgentActivityUpdated $event): void
    {
        try {
            event($event);
        } catch (Throwable $exception) {
            Log::warning('[reception] 实时推送失败', [
                'event' => $event::class,
                'topic' => $event->broadcastOn()->name,
                'exception_class' => $exception::class,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
