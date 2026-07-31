<?php

namespace App\Enums;

/** Telegram 入站 Update 的处理状态。 */
enum TelegramInboundUpdateStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
