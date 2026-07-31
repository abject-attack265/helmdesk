<?php

namespace App\Data\AiChat;

use Spatie\LaravelData\Data;

/**
 * AI 助手历史关键词检索命中的消息位置。
 */
class AiAssistantHistoryMatchData extends Data
{
    /**
     * 保存命中消息的位置和建议读取窗口。
     *
     * @param  list<string>  $matched_keywords
     */
    public function __construct(
        public string $thread_id,
        public int $message_offset,
        public string $occurred_at,
        public array $matched_keywords,
        public string $preview,
        public int $suggested_offset,
        public int $suggested_limit,
    ) {}
}
