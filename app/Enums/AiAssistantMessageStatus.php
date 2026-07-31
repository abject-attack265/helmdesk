<?php

namespace App\Enums;

/**
 * AI 助手消息的生成状态。
 */
enum AiAssistantMessageStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
