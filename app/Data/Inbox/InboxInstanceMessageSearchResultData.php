<?php

namespace App\Data\Inbox;

use Spatie\LaravelData\Data;

/**
 * 收件箱全应用消息搜索结果，供 InboxGlobalMessageSearchList.vue 展示。
 */
class InboxInstanceMessageSearchResultData extends Data
{
    /**
     * 承载消息命中内容及其所属线程和联系人。
     */
    public function __construct(
        public string $id,
        public string $thread_id,
        public string $contact_id,
        public ?string $contact_name,
        public ?string $contact_avatar_url,
        public string $role,
        public string $role_label,
        public string $kind,
        public ?string $sender_name,
        public ?string $content,
        public string $matched_content,
        public string $occurred_at,
    ) {}
}
