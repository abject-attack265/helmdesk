<?php

namespace App\Data\Conversation;

use App\Enums\ConversationEventSemanticType;
use App\Enums\ConversationEventTone;
use Spatie\LaravelData\Data;

/**
 * 客服时间线的会话事件展示数据。
 */
class ConversationEventDisplayData extends Data
{
    /**
     * 创建会话事件展示数据。
     *
     * @param  ConversationEventFactData[]  $facts
     */
    public function __construct(
        public string $summary,
        public ?string $detail,
        public ConversationEventSemanticType $semantic_type,
        public ConversationEventTone $tone,
        public array $facts = [],
    ) {}
}
