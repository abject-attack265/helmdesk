<?php

namespace App\Data\Inbox;

use App\Data\Contact\ContactStitchedTimelineData;
use App\Data\Conversation\ConversationContactSummaryData;
use App\Data\Conversation\ConversationSummaryData;
use App\Data\Reception\ReceptionActivityStateData;
use Spatie\LaravelData\Data;

/**
 * 收件箱当前选中线程详情，供 Inbox.vue 展示会话、时间线和联系人资料。
 */
class InboxSelectionData extends Data
{
    /**
     * 承载当前线程的会话、联系人、时间线与可用操作。
     */
    public function __construct(
        public ConversationSummaryData $conversation,
        public ReceptionActivityStateData $agent_activity,
        public ConversationContactSummaryData $contact,
        public InboxContactProfileData $contact_profile,
        public ContactStitchedTimelineData $stitched_timeline,
        public bool $can_reply,
        public bool $can_claim,
        public bool $can_transfer_to_teammate,
        public bool $can_release_to_ai,
        public bool $release_to_ai_will_use_ai,
        public bool $can_close,
        public bool $can_reopen,
        public bool $can_translate_messages,
    ) {}
}
