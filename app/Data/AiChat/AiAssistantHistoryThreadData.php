<?php

namespace App\Data\AiChat;

use Spatie\LaravelData\Data;

/**
 * 注入 AI 助手系统提示词的历史线程目录项。
 */
class AiAssistantHistoryThreadData extends Data
{
    /**
     * 保存历史线程标识、消息数量和时间范围。
     */
    public function __construct(
        public string $id,
        public int $message_count,
        public string $started_at,
        public string $last_message_at,
    ) {}
}
