<?php

namespace App\Enums;

/** 微信公众号入站消息的持久化处理状态。 */
enum WechatInboundMessageStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';
}
