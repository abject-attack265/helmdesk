<?php

namespace App\Data\Experience;

use App\Models\Conversation;
use Spatie\LaravelData\Data;

/**
 * 经验提炼的单条会话项，两处消费：
 * - resources/js/pages/experiences/Create.vue：展开联系人后的会话明细
 * - resources/js/pages/experiences/Conversations.vue：任务消费的会话清单，跨联系人平铺，用得上访客字段
 *
 * 创建页里含没有人工消息的会话：访客沉默被自动关闭后隔天再来会新开一条会话，提问与人工答复因此被切成两条，
 * 把联系人窗口内的会话都带上，提问的上下文才不丢。
 */
class ListExtractableConversationItemData extends Data
{
    public function __construct(
        public string $id,
        public string $subject,
        public ?string $contact_id,
        public ?string $contact_name,
        public ?string $last_message_preview,
        public string $closed_at,
        public int $teammate_message_count,
        public bool $already_extracted,
    ) {}

    /**
     * 从会话模型构造列表项；teammate_message_count 由查询侧 withCount 提供，contact 需预加载。
     */
    public static function fromModel(Conversation $conversation, bool $alreadyExtracted): self
    {
        return new self(
            id: (string) $conversation->id,
            subject: trim((string) $conversation->subject),
            contact_id: $conversation->contact_id !== null ? (string) $conversation->contact_id : null,
            contact_name: $conversation->contact?->name,
            last_message_preview: $conversation->last_message_preview,
            closed_at: $conversation->closed_at?->toIso8601String() ?? '',
            teammate_message_count: (int) ($conversation->teammate_messages_count ?? 0),
            already_extracted: $alreadyExtracted,
        );
    }
}
