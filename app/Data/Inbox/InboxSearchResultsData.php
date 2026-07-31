<?php

namespace App\Data\Inbox;

use Spatie\LaravelData\Data;

/**
 * 收件箱左侧搜索的分组结果，消费于收件箱搜索面板（Inbox.vue）：
 * 联系人分组在上、聊天记录分组在下。限定「此聊天」范围时联系人分组恒为空。
 */
class InboxSearchResultsData extends Data
{
    /**
     * @param  list<InboxContactSearchResultData>  $contacts
     * @param  list<InboxInstanceMessageSearchResultData>  $messages
     */
    public function __construct(
        public array $contacts,
        public array $messages,
    ) {}
}
