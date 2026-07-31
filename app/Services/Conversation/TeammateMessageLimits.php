<?php

namespace App\Services\Conversation;

/**
 * 定义客服单次发送消息的内容与附件数量限制。
 */
final class TeammateMessageLimits
{
    public const int MAX_CONTENT_LENGTH = 8000;

    public const int MAX_ATTACHMENT_COUNT = 10;
}
