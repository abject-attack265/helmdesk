<?php

namespace App\Data\Conversation;

use App\Enums\MessageRole;
use Spatie\LaravelData\Data;

/**
 * AI 会话上下文中的一条标准化消息，供接待与员工助手按各自语义转换。
 */
class ConversationAiContextMessageData extends Data
{
    /**
     * 承载消息角色和包含附件占位的正文。
     */
    public function __construct(
        public MessageRole $role,
        public string $content,
    ) {}
}
