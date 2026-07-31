<?php

namespace App\Data\Inbox;

use Spatie\LaravelData\Data;

/**
 * 收件箱 AI 回复助手上下文，提供客服语言、访客语言、摘要、引用消息和最近文本消息。
 */
class InboxReplyPolishContextData extends Data
{
    /**
     * 承载一次性润色所需的只读上下文。
     *
     * @param  InboxReplyPolishMessageContextData[]  $recent_messages
     */
    public function __construct(
        public ?string $visitor_locale,
        public ?string $teammate_locale,
        public ?string $conversation_subject,
        public ?string $conversation_summary,
        public ?InboxReplyPolishMessageContextData $quoted_message,
        public array $recent_messages,
    ) {}
}
