<?php

namespace App\Data\Conversation;

use Spatie\LaravelData\Data;

/**
 * 会话摘要的结构化生成结果。
 */
class GeneratedConversationSummaryData extends Data
{
    /** 创建会话摘要生成结果。 */
    public function __construct(
        public string $summary,
    ) {}
}
