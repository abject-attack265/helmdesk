<?php

namespace App\Services\Reception;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * 接待 turn 的协作式抢占信号。
 *
 * PHP 无法瞬间杀掉飞行中的流式推理，
 * 因此降级为协作式——正在跑的 turn 在 stream 的 chunk / tool 边界轮询本信号，命中即丢弃产物退出。
 *
 * 取消标志携带它所针对的 turnId：只有「当前 turn 的 id 与标志一致」时才中止；
 * 每个新 turn 使用全新 turnId，因此天然免疫上一轮遗留的过期标志，无需显式清理竞态。
 *
 * 全部状态存放在数据库缓存中。
 */
class ReceptionPreemptionSignal
{
    /**
     * 抢占标志的存活时长：覆盖单轮 turn 的最大可能时长即可，过期自动清理。
     */
    private const int TTL_SECONDS = 600;

    /**
     * 解析底层缓存仓库（默认 store 由 CACHE_STORE 决定）。
     */
    private function cache(): CacheRepository
    {
        return Cache::store();
    }

    /**
     * 请求抢占指定会话「当前正在运行的 turn」。
     *
     * 由新访客消息到达时调用：把取消标志指向该会话当前登记的 turnId。
     * 若此刻没有 turn 在跑（current 为空），写入空标志不会误伤后续新 turn。
     */
    public function requestPreemption(string $conversationId): void
    {
        $currentTurnId = (string) $this->cache()->get($this->currentKey($conversationId), '');
        if ($currentTurnId === '') {
            return;
        }

        $this->cache()->put($this->cancelKey($conversationId), $currentTurnId, self::TTL_SECONDS);
    }

    /**
     * 登记某会话当前正在运行的 turn，并清除可能遗留的旧取消标志。
     *
     * 在 RunReceptionTurnJob 取得单飞锁、正式开跑前调用。
     */
    public function beginTurn(string $conversationId, string $turnId): void
    {
        $this->cache()->put($this->currentKey($conversationId), $turnId, self::TTL_SECONDS);
        $this->cache()->forget($this->cancelKey($conversationId));
    }

    /**
     * 判断指定 turn 是否已被请求抢占。
     *
     * 由 turn 的 stream 消费循环在 chunk / tool 边界轮询；返回 true 时调用方应丢弃产物并退出。
     */
    public function isPreempted(string $conversationId, string $turnId): bool
    {
        return (string) $this->cache()->get($this->cancelKey($conversationId), '') === $turnId;
    }

    /**
     * turn 结束时清理本会话的当前 turn 登记与取消标志。
     */
    public function endTurn(string $conversationId, string $turnId): void
    {
        if ((string) $this->cache()->get($this->currentKey($conversationId), '') === $turnId) {
            $this->cache()->forget($this->currentKey($conversationId));
        }
        $this->cache()->forget($this->cancelKey($conversationId));
    }

    /**
     * 「当前运行 turn」登记键。
     */
    private function currentKey(string $conversationId): string
    {
        return 'reception:turn:current:'.$conversationId;
    }

    /**
     * 取消标志键。
     */
    private function cancelKey(string $conversationId): string
    {
        return 'reception:turn:cancel:'.$conversationId;
    }
}
