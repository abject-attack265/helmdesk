<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 消息内容类型，区分文本、工具调用和工具结果。
 */
enum MessageKind: string implements LabeledEnum
{
    case Text = 'text';
    case Image = 'image';
    case File = 'file';
    case Summary = 'summary';
    case ToolCall = 'tool_call';
    case ToolResult = 'tool_result';

    public function label(): string
    {
        return match ($this) {
            self::Text => __('conversation.message_kinds.text'),
            self::Image => __('conversation.message_kinds.image'),
            self::File => __('conversation.message_kinds.file'),
            self::Summary => __('conversation.message_kinds.summary'),
            self::ToolCall => __('conversation.message_kinds.tool_call'),
            self::ToolResult => __('conversation.message_kinds.tool_result'),
        };
    }
}
