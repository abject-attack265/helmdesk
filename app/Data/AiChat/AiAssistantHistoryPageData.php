<?php

namespace App\Data\AiChat;

use Spatie\LaravelData\Data;

/**
 * AI 助手历史工具按位置读取的一页连续消息。
 */
class AiAssistantHistoryPageData extends Data
{
    /**
     * 保存线程分页位置、总消息数和连续消息正文。
     *
     * @param  list<array{offset: int, round_id: string, role: string, content: string, occurred_at: string}>  $messages
     */
    public function __construct(
        public string $thread_id,
        public int $offset,
        public int $limit,
        public int $total_messages,
        public bool $has_more,
        public array $messages,
    ) {}
}
