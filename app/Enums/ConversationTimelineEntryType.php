<?php

namespace App\Enums;

/**
 * 会话时间线索引条目类型，指向消息或事件事实表。
 */
enum ConversationTimelineEntryType: string
{
    case Message = 'message';
    case Event = 'event';
}
