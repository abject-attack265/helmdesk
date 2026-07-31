<?php

namespace App\Data\Conversation;

use Spatie\LaravelData\Data;

/**
 * 会话事件详情事实项。
 * 由事件展示构建器下发给时间线组件，用于渲染客服可读的 key-value 详情。
 */
class ConversationEventFactData extends Data
{
    /**
     * 创建一个事件详情事实项。
     */
    public function __construct(
        public string $label,
        public string $value,
    ) {}
}
