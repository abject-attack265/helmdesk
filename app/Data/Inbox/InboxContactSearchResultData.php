<?php

namespace App\Data\Inbox;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationThread;
use LogicException;
use Spatie\LaravelData\Data;

/**
 * 收件箱左侧联系人搜索结果，供 InboxGlobalContactSearchList.vue 展示。
 */
class InboxContactSearchResultData extends Data
{
    /**
     * 承载联系人及其最近活跃线程的搜索摘要。
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $avatar_url,
        public string $thread_id,
        public ?string $last_message_preview,
        public ?string $last_message_at,
    ) {}

    /**
     * 由联系人及其最近活跃线程组装搜索结果。
     */
    public static function fromModel(Contact $contact, ConversationThread $thread): self
    {
        $conversation = $thread->currentConversation;
        if (! $conversation instanceof Conversation) {
            throw new LogicException("收件箱线程 {$thread->id} 缺少当前代表会话。");
        }

        return new self(
            id: (string) $contact->id,
            name: $contact->name,
            avatar_url: $contact->avatar_url,
            thread_id: (string) $thread->id,
            last_message_preview: $conversation->last_message_preview,
            last_message_at: $conversation->last_message_at?->toIso8601String(),
        );
    }
}
