<?php

namespace App\Data\Conversation;

use App\Data\User\UserOptionData;
use Spatie\LaravelData\Data;

/**
 * 会话详情数据。
 */
class ConversationDetailData extends Data
{
    public function __construct(
        public ConversationSummaryData $conversation,
        public ?ConversationContactSummaryData $contact_summary,
        public ?ConversationReceptionPlanVersionSummaryData $reception_plan_version_summary,
        public ?UserOptionData $assigned_teammate,
        public ConversationTimelineData $timeline,
    ) {}
}
