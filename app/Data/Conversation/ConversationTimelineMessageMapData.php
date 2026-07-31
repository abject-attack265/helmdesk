<?php

namespace App\Data\Conversation;

use Spatie\LaravelData\Data;

/**
 * 时间线页面内消息 payload 与引用消息的展示映射。
 */
class ConversationTimelineMessageMapData extends Data
{
    /**
     * 保存按消息 ID 索引的 payload 和引用消息快照。
     *
     * @param  array<string, array<string, mixed>|null>  $message_payloads
     * @param  array<string, QuotedMessageData>  $quoted_messages
     */
    public function __construct(
        public array $message_payloads,
        public array $quoted_messages,
    ) {}
}
