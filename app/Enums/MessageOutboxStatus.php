<?php

namespace App\Enums;

/** 外部渠道消息投递状态。 */
enum MessageOutboxStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
}
