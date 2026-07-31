<?php

namespace App\Data\AiChat;

use Spatie\LaravelData\Data;

/**
 * 一轮持久化 AI 助手问答的线程与消息标识。
 */
class AiAssistantRoundData extends Data
{
    /**
     * 保存本轮线程、客服消息和待生成助手消息的标识。
     */
    public function __construct(
        public string $thread_id,
        public string $user_message_id,
        public string $assistant_message_id,
    ) {}
}
