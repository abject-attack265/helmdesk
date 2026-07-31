<?php

namespace App\Services\Telegram;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * 串行处理 Telegram 渠道连接和机器人绑定。
 */
class TelegramChannelConnectionLock
{
    /** 锁覆盖 Token 校验、机器人绑定和 webhook 注册。 */
    private const int LOCK_TTL_SECONDS = 120;

    /**
     * 在指定渠道的连接锁内执行操作。
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function runForChannel(string $channelId, Closure $callback): mixed
    {
        return $this->run('telegram-channel-connection:'.$channelId, $callback);
    }

    /**
     * 在指定机器人的绑定锁内执行操作。
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function runForBot(int $botId, Closure $callback): mixed
    {
        return $this->run('telegram-bot-binding:'.$botId, $callback);
    }

    /**
     * 等待并持有连接锁后执行操作。
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    private function run(string $key, Closure $callback): mixed
    {
        return Cache::lock($key, self::LOCK_TTL_SECONDS)
            ->block(15, $callback);
    }
}
