<?php

namespace App\Data\Inbox;

use Spatie\LaravelData\Data;

/**
 * 收件箱 AI 润色使用的单条会话消息上下文。
 */
class InboxReplyPolishMessageContextData extends Data
{
    /**
     * 保存模型理解对话所需的轻量消息字段。
     */
    public function __construct(
        public string $role,
        public string $sender_name,
        public string $content,
        public ?string $occurred_at,
    ) {}
}
