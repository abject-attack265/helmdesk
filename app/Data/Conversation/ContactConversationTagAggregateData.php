<?php

namespace App\Data\Conversation;

use Spatie\LaravelData\Data;

/**
 * 联系人「咨询概况」聚合项。
 * 接待页右侧面板按联系人维度，把其所有会话上的会话标签去重计数后只读展示（如「退款 ×5」）。
 */
class ContactConversationTagAggregateData extends Data
{
    public function __construct(
        public string $tag_id,
        public string $name,
        public ?string $color,
        public int $count,
    ) {}
}
