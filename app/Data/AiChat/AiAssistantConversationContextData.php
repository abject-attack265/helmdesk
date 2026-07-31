<?php

namespace App\Data\AiChat;

use Spatie\LaravelData\Data;

/**
 * 右侧 AI 助手每轮使用的当前客户会话背景。
 */
class AiAssistantConversationContextData extends Data
{
    /**
     * 承载发送给模型的系统背景文本。
     */
    public function __construct(
        public string $context,
    ) {}
}
