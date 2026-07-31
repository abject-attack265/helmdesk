<?php

namespace App\Services\Telegram;

/**
 * 构造 Telegram 渠道的入站 webhook 公网地址。
 *
 * Telegram 要求 webhook 为公网可达的 HTTPS URL；地址由 config('app.url') 与渠道公开 code 拼成，
 * 入站请求由 /webhook/telegram/:code 承接。
 */
class TelegramWebhookUrl
{
    /**
     * 返回指定渠道 code 对应的 webhook 地址。
     */
    public static function for(string $code): string
    {
        return rtrim((string) config('app.url'), '/').'/webhook/telegram/'.$code;
    }
}
