<?php

namespace App\Services\AiChat;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * 通过缓存标记 AI 助手轮次，供流式任务在片段边界停止生成。
 */
class AiChatStreamSignal
{
    /**
     * 取消标志存活时长：覆盖单轮流式输出的最大可能时长即可。
     */
    private const int TTL_SECONDS = 600;

    /**
     * 解析底层缓存仓库。
     */
    private function cache(): CacheRepository
    {
        return Cache::store();
    }

    /**
     * 请求取消指定轮次上正在进行的流式输出。
     */
    public function requestCancel(string $key): void
    {
        $this->cache()->put($this->cacheKey($key), true, self::TTL_SECONDS);
    }

    /**
     * 判断指定轮次是否已被请求取消。
     */
    public function isCancelled(string $key): bool
    {
        return (bool) $this->cache()->get($this->cacheKey($key), false);
    }

    /**
     * 清理指定轮次的取消标志（流式结束时调用）。
     */
    public function clear(string $key): void
    {
        $this->cache()->forget($this->cacheKey($key));
    }

    /**
     * 取消标志缓存键。
     */
    private function cacheKey(string $key): string
    {
        return 'ai-chat:stream:cancel:'.hash('sha256', $key);
    }
}
