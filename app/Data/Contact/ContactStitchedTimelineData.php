<?php

namespace App\Data\Contact;

use App\Data\Conversation\ConversationSummaryData;
use Spatie\LaravelData\Data;

/**
 * 联系人会话拼接时间线数据。
 * 显示在收件箱和联系人详情里，承载会话摘要、时间线窗口和 keyset 游标。
 */
class ContactStitchedTimelineData extends Data
{
    /**
     * 保存当前窗口的会话摘要、联系人内会话序号、时间线条目和游标。
     */
    public function __construct(
        public string $contact_id,
        /** @var ConversationSummaryData[] */
        public array $conversations,
        /** @var array<string, int> */
        public array $conversation_sequence_by_id,
        /** @var ContactTimelineEntryData[] */
        public array $entries,
        public ?string $previous_cursor,
        public ?string $next_cursor,
        public ?string $anchor_entry_id = null,
    ) {}
}
