<?php

namespace App\Data\Experience;

use App\Models\Contact;
use Spatie\LaravelData\Data;

/**
 * 经验提炼「创建提炼任务」页的联系人列表项，
 * 用于 resources/js/pages/experiences/Create.vue 的勾选列表。
 *
 * 提炼以联系人为单位：勾选一个联系人即把它在窗口内的全部已关闭会话按时间顺序拼成一段转录送给 LLM，
 * 让被自动关闭切开的提问与人工答复重新连上。conversation_count 即实际送入的会话数，前端据此累计上限。
 */
class ListExtractableContactItemData extends Data
{
    public function __construct(
        public string $id,
        public ?string $name,
        /** 窗口内最近一次会话关闭时间。 */
        public string $last_closed_at,
        /** 窗口内已关闭会话数（= 提炼时送入的会话数）。 */
        public int $conversation_count,
        /** 窗口内人工坐席文本消息总数。 */
        public int $teammate_message_count,
        /** 窗口内会话是否已全部被本问答库提炼过，前端据此默认跳过。 */
        public bool $already_extracted,
        /** @var ListExtractableConversationItemData[] */
        public array $conversations,
    ) {}

    /**
     * 从联系人与其窗口内会话明细构造列表项；$conversations 须按关闭时间正序（查询侧已保证）。
     *
     * @param  list<ListExtractableConversationItemData>  $conversations
     */
    public static function fromModel(Contact $contact, array $conversations): self
    {
        return new self(
            id: (string) $contact->id,
            name: $contact->name,
            last_closed_at: array_last($conversations)?->closed_at ?? '',
            conversation_count: count($conversations),
            teammate_message_count: array_sum(array_map(
                static fn (ListExtractableConversationItemData $c): int => $c->teammate_message_count,
                $conversations,
            )),
            already_extracted: $conversations !== [] && array_all(
                $conversations,
                static fn (ListExtractableConversationItemData $c): bool => $c->already_extracted,
            ),
            conversations: $conversations,
        );
    }
}
