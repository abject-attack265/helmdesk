<?php

namespace App\Data\AiChat;

use Spatie\LaravelData\Data;

/**
 * AI 助手面板连续时间线中的一段独立对话。
 */
class AiAssistantThreadData extends Data
{
    /**
     * 保存线程标识和按顺序排列的消息。
     *
     * @param  list<AiAssistantMessageData>  $messages
     */
    public function __construct(
        public string $id,
        public array $messages,
    ) {}
}
