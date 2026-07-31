<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 消息投递状态，标识从产生到送达接收端各阶段。
 *
 * 网站渠道：消息落库即视为 Sent（访客端经实时广播频道接收）。
 * Telegram 等需主动推送的外部渠道：出站消息先置 Sending，发送 Job 成功翻 Sent、失败翻 Failed；
 * 外部渠道消息由 Message Outbox 对账任务恢复卡住的投递。
 */
enum MessageDeliveryStatus: string implements LabeledEnum
{
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Sending => __('conversation.message_delivery_statuses.sending'),
            self::Sent => __('conversation.message_delivery_statuses.sent'),
            self::Failed => __('conversation.message_delivery_statuses.failed'),
        };
    }
}
