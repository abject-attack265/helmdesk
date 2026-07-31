<?php

namespace App\Data\Conversation;

use App\Models\Contact;
use Spatie\LaravelData\Data;

/**
 * 会话联系人摘要数据。
 * 由后端组装后传给 resources/js/pages/contacts/Conversation.vue、ConversationDetailDrawer.vue 和 inbox/InboxStitchedTimeline.vue，用于页面展示、抽屉详情或局部交互状态。
 */
class ConversationContactSummaryData extends Data
{
    public function __construct(
        public string $id,
        public ?string $name,
        public string $avatar_url,
        public ?string $primary_email,
        public ?string $primary_phone,
        public bool $is_important,
    ) {}

    public static function fromModel(Contact $contact): self
    {
        return new self(
            id: $contact->id,
            name: $contact->name,
            avatar_url: $contact->avatar_url,
            primary_email: $contact->primary_email,
            primary_phone: $contact->primary_phone,
            is_important: $contact->is_important,
        );
    }
}
