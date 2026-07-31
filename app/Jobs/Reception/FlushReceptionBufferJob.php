<?php

namespace App\Jobs\Reception;

use App\Actions\Reception\DispatchReceptionTurnAction;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use App\Services\Reception\ReceptionDebouncer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * 在访客输入静默窗口结束后拉取聚合消息并派发接待轮次。
 *
 * 同一会话的延迟任务通过单飞锁和原子拉取收敛为一次有效派发。
 */
class FlushReceptionBufferJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * 创建指定会话的缓冲刷新任务。
     */
    public function __construct(
        public readonly string $conversationId,
    ) {
        $this->queue = 'reception-buffer';
    }

    /**
     * 同会话单飞 flush；重叠的 flush 直接丢弃（由正在跑的那个覆盖）。
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('reception-flush:'.$this->conversationId))
                ->dontRelease()
                ->expireAfter(30),
        ];
    }

    /**
     * 判定静默窗口，并在拉取缓冲后停止 debounce 活动和派发接待轮次。
     */
    public function handle(
        ReceptionDebouncer $debouncer,
        ReceptionRealtimeNotifier $realtimeNotifier,
        DispatchReceptionTurnAction $dispatchTurn,
    ): void {
        $wait = max(
            $debouncer->typingHoldRemainingMs($this->conversationId),
            $debouncer->remainingUntilDueMs($this->conversationId),
        );

        if ($wait > 0) {
            // 仍在输入或窗口被顺延：推迟到该时刻后重试，避免半句话就触发回复。
            self::dispatch($this->conversationId)->delay(now()->addMilliseconds($wait));

            return;
        }

        $pulled = $debouncer->pullPending($this->conversationId);
        if ($pulled === null) {
            $realtimeNotifier->aiDebounceStopped($this->conversationId);

            return;
        }

        try {
            $dispatchTurn->handle(
                $this->conversationId,
                $pulled['text'],
                $pulled['message_ids'],
                $pulled['media_ids'],
            );
        } finally {
            $realtimeNotifier->aiDebounceStopped($this->conversationId);
        }
    }
}
