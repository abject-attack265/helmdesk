<?php

namespace App\Services\Reception;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * 访客消息的自适应 debounce 聚合。
 *
 * 客服访客常一句一句连发，没必要每条都触发一轮 ReAct。
 * 缓冲存放在 Cache（database / redis 驱动均可），由 FlushReceptionBufferJob 在静默窗口结束后拉取聚合。
 *
 * 窗口策略：
 *   - 基础静默窗口 2000ms；缓冲里每多堆积一条未 flush 的消息，窗口顺延 700ms，封顶 4000ms；
 *   - 访客仍在输入（前端上报 typing）时把 flush 推迟到输入静默之后（typingHold 4000ms + grace 300ms），避免半句话被回复。
 *
 * 缓冲的读改写用 Cache::lock 串行化，避免多 worker 并发 append 丢消息。
 */
class ReceptionDebouncer
{
    /** 基础静默窗口（毫秒）。 */
    public const int WINDOW_BASE_MS = 2000;

    /** 每多一条待处理消息顺延的增量（毫秒）。 */
    public const int WINDOW_STEP_MS = 700;

    /** 自适应窗口上限（毫秒）。 */
    public const int WINDOW_MAX_MS = 4000;

    /** 一次 typing 信号的有效期（毫秒）：需大于前端 typing 上报节流间隔。 */
    public const int TYPING_HOLD_MS = 4000;

    /** typing 静默到真正 flush 之间的缓冲（毫秒）。 */
    public const int TYPING_GRACE_MS = 300;

    /** 缓冲与 typing 状态的存活时长（秒）。 */
    private const int STATE_TTL_SECONDS = 600;

    /**
     * 解析底层缓存仓库（默认 store 由 CACHE_STORE 决定）。
     */
    private function cache(): CacheRepository
    {
        return Cache::store();
    }

    /**
     * 按缓冲里已堆积的消息条数计算本次静默窗口（毫秒）。
     *
     * 单条仍是基础窗口；连发越多窗口越长，封顶在 WINDOW_MAX_MS。
     */
    public function windowForPending(int $pendingCount): int
    {
        if ($pendingCount <= 1) {
            return self::WINDOW_BASE_MS;
        }

        $window = self::WINDOW_BASE_MS + self::WINDOW_STEP_MS * ($pendingCount - 1);

        return min($window, self::WINDOW_MAX_MS);
    }

    /**
     * 写入一条访客消息；同一 messageId 只接受一次。
     *
     * @param  list<string>  $mediaIds
     */
    public function acceptOnce(string $conversationId, string $text, string $messageId, array $mediaIds = []): ?int
    {
        return $this->withBufferLock($conversationId, function () use ($conversationId, $text, $messageId, $mediaIds): ?int {
            $state = $this->readState($conversationId);
            $pending = $state['pending'];
            $acceptedAt = $this->pruneAcceptedAt($state['accepted_at']);

            if ($messageId !== '' && isset($acceptedAt[$messageId])) {
                return null;
            }

            $pending[] = ['text' => $text, 'id' => $messageId, 'media' => array_values($mediaIds)];
            if ($messageId !== '') {
                $acceptedAt[$messageId] = now()->getTimestamp();
            }
            $this->saveState($conversationId, [
                'pending' => $pending,
                'accepted_at' => $acceptedAt,
            ]);

            $window = $this->windowForPending(count($pending));

            $dueAt = now()->getPreciseTimestamp(3) + $window;
            $this->cache()->put($this->dueKey($conversationId), $dueAt, self::STATE_TTL_SECONDS);

            return $window;
        });
    }

    /**
     * 处理访客撤回：仅作用于尚未 flush 的缓冲消息；已进入处理流程的消息撤回在此为 no-op。
     */
    public function acceptRecall(string $conversationId, string $messageId): void
    {
        if ($messageId === '') {
            return;
        }

        $this->withBufferLock($conversationId, function () use ($conversationId, $messageId): void {
            $state = $this->readState($conversationId);
            $pending = $state['pending'];
            $filtered = array_values(array_filter(
                $pending,
                static fn (array $m): bool => $m['id'] !== $messageId,
            ));
            $this->saveState($conversationId, [
                'pending' => $filtered,
                'accepted_at' => $this->pruneAcceptedAt($state['accepted_at']),
            ]);
        });
    }

    /**
     * 记录一次入站 typing 信号：把「访客仍在输入」截止时刻顺延 TYPING_HOLD_MS。
     */
    public function noteTyping(string $conversationId): void
    {
        $until = now()->addMilliseconds(self::TYPING_HOLD_MS)->getPreciseTimestamp(3) / 1000;
        $this->cache()->put($this->typingKey($conversationId), $until, self::STATE_TTL_SECONDS);
    }

    /**
     * 返回距离「输入静默」还剩多少毫秒（含 grace）；已静默则返回 0。
     *
     * flush 触发时若返回 >0，应把聚合推迟该毫秒数后重试，避免访客半句话就被回复。
     */
    public function typingHoldRemainingMs(string $conversationId): int
    {
        $until = (float) $this->cache()->get($this->typingKey($conversationId), 0);
        if ($until <= 0) {
            return 0;
        }

        $nowSeconds = now()->getPreciseTimestamp(3) / 1000;
        $remainingMs = (int) ceil(($until - $nowSeconds) * 1000);
        if ($remainingMs <= 0) {
            return 0;
        }

        return $remainingMs + self::TYPING_GRACE_MS;
    }

    /**
     * 返回距离「自适应静默窗口到期」还剩多少毫秒；已到期或无记录则返回 0。
     *
     * flush job 触发时若返回 >0，说明此后还有更晚的消息把窗口顺延了，应推迟该毫秒数后重试。
     */
    public function remainingUntilDueMs(string $conversationId): int
    {
        $dueAt = (float) $this->cache()->get($this->dueKey($conversationId), 0);
        if ($dueAt <= 0) {
            return 0;
        }

        $remaining = (int) ceil($dueAt - now()->getPreciseTimestamp(3));

        return max(0, $remaining);
    }

    /**
     * 原子拉取并清空缓冲，返回聚合后的 user 消息文本、有序的访客文本消息 ID、以及图片/视频消息 ID。
     *
     * 多条消息的非空文本按时间顺序用换行拼接（纯媒体消息文本为空，不占行）；缓冲为空时返回 null。
     *
     * @return array{text: string, message_ids: list<string>, media_ids: list<string>}|null
     */
    public function pullPending(string $conversationId): ?array
    {
        return $this->withBufferLock($conversationId, function () use ($conversationId): ?array {
            $state = $this->readState($conversationId);
            $pending = $state['pending'];
            if ($pending === []) {
                return null;
            }

            $this->saveState($conversationId, [
                'pending' => [],
                'accepted_at' => $this->pruneAcceptedAt($state['accepted_at']),
            ]);
            $this->cache()->forget($this->dueKey($conversationId));

            $texts = [];
            $ids = [];
            $mediaIds = [];
            foreach ($pending as $message) {
                if ($message['text'] !== '') {
                    $texts[] = $message['text'];
                }
                if ($message['id'] !== '') {
                    $ids[] = $message['id'];
                }
                foreach ($message['media'] as $mediaId) {
                    if ($mediaId !== '') {
                        $mediaIds[] = $mediaId;
                    }
                }
            }

            return [
                'text' => implode("\n", $texts),
                'message_ids' => $ids,
                'media_ids' => $mediaIds,
            ];
        });
    }

    /** @return array{pending: list<array{text: string, id: string, media: list<string>}>, accepted_at: array<string, int>} */
    private function readState(string $conversationId): array
    {
        return $this->cache()->get($this->bufferKey($conversationId), [
            'pending' => [],
            'accepted_at' => [],
        ]);
    }

    /**
     * @param  array{pending: list<array{text: string, id: string, media: list<string>}>, accepted_at: array<string, int>}  $state
     */
    private function saveState(string $conversationId, array $state): void
    {
        if ($state['pending'] === [] && $state['accepted_at'] === []) {
            $this->cache()->forget($this->bufferKey($conversationId));

            return;
        }

        $this->cache()->put($this->bufferKey($conversationId), $state, self::STATE_TTL_SECONDS);
    }

    /**
     * 连续活跃会话也只保留最近十分钟的消息幂等键，限制缓存增长。
     *
     * @param  array<string, mixed>  $acceptedAt
     * @return array<string, int>
     */
    private function pruneAcceptedAt(array $acceptedAt): array
    {
        $cutoff = now()->subSeconds(self::STATE_TTL_SECONDS)->getTimestamp();

        return array_filter(
            $acceptedAt,
            static fn (mixed $acceptedAt): bool => is_int($acceptedAt) && $acceptedAt >= $cutoff,
        );
    }

    /**
     * 在会话级缓冲锁内执行回调，串行化缓冲读改写。
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withBufferLock(string $conversationId, callable $callback): mixed
    {
        return $this->cache()->lock($this->lockKey($conversationId), 5)->block(5, $callback);
    }

    private function bufferKey(string $conversationId): string
    {
        return 'reception:debounce:buffer:'.$conversationId;
    }

    private function typingKey(string $conversationId): string
    {
        return 'reception:debounce:typing:'.$conversationId;
    }

    private function dueKey(string $conversationId): string
    {
        return 'reception:debounce:due:'.$conversationId;
    }

    private function lockKey(string $conversationId): string
    {
        return 'reception:debounce:lock:'.$conversationId;
    }
}
