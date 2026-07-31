<?php

namespace App\Data\AiChat;

use App\Enums\AiAssistantMessageSegmentType;
use Spatie\LaravelData\Data;

/**
 * AI 助手面板恢复回答文本和工具事件时使用的有序片段数据。
 */
class AiAssistantMessageSegmentData extends Data
{
    /**
     * 保存文本内容或工具调用的名称、参数与结果。
     */
    public function __construct(
        public AiAssistantMessageSegmentType $type,
        public ?string $content = null,
        public ?string $tool = null,
        public ?string $tool_display = null,
        public ?string $args = null,
    ) {}
}
