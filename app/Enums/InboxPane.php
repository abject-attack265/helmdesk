<?php

namespace App\Enums;

/**
 * 收件箱在窄屏设备上的当前面板。
 */
enum InboxPane: string
{
    case List = 'list';
    case Thread = 'thread';
}
