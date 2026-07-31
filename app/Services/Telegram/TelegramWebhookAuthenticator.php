<?php

namespace App\Services\Telegram;

use App\Enums\ChannelType;
use App\Models\Channel;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 校验 Telegram webhook 回调携带的 secret 头并返回对应渠道。
 *
 * secret 仅存于服务端：请求头携带的 secret 由此处与渠道存储的 webhook_secret 做恒定时间比较。
 * 文本与媒体两类入站共用本鉴权，避免各自重复渠道查询与 secret 校验。
 */
class TelegramWebhookAuthenticator
{
    /**
     * 校验 secret 并返回渠道；渠道不存在抛 404，secret 不符抛 403。
     */
    public function authenticate(string $code, string $secretToken): Channel
    {
        $channel = Channel::query()
            ->withTrashed()
            ->where('code', $code)
            ->where('type', ChannelType::Telegram)
            ->first();

        if ($channel === null) {
            throw new NotFoundHttpException;
        }

        $settings = $channel->telegramSettings();

        if ($settings->webhook_secret === '' || ! hash_equals($settings->webhook_secret, $secretToken)) {
            throw new AccessDeniedHttpException('invalid telegram webhook secret');
        }

        return $channel;
    }
}
