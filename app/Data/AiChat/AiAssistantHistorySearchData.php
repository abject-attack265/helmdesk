<?php

namespace App\Data\AiChat;

use Spatie\LaravelData\Data;

/**
 * AI 助手历史工具的关键词检索结果页。
 */
class AiAssistantHistorySearchData extends Data
{
    /**
     * 保存检索分页信息和命中位置。
     *
     * @param  list<AiAssistantHistoryMatchData>  $matches
     */
    public function __construct(
        public int $offset,
        public int $limit,
        public bool $has_more,
        public array $matches,
    ) {}
}
