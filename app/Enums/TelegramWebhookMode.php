<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * Telegram 渠道 webhook 归属模式。
 *
 * Direct：本系统直连 Telegram，webhook 注册到本系统；
 * Gateway：webhook 由外部业务网关托管（网关接收后把私聊消息原样转发过来），
 * 此模式下禁止在本系统注册 webhook，否则会把 webhook 从网关手中抢走导致业务事件丢失。
 */
enum TelegramWebhookMode: string implements LabeledEnum
{
    case Direct = 'direct';
    case Gateway = 'gateway';

    public function label(): string
    {
        return match ($this) {
            self::Direct => __('channel.telegram.webhook_modes.direct'),
            self::Gateway => __('channel.telegram.webhook_modes.gateway'),
        };
    }
}
