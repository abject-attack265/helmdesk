<?php

namespace App\Enums;

/**
 * AI 助手回答中可持久化并恢复到界面的片段类型。
 */
enum AiAssistantMessageSegmentType: string
{
    case Text = 'text';
    case ToolCall = 'tool_call';
    case ToolResult = 'tool_result';
}
