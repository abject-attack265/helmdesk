<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 消息发送角色，标识访客、AI 或客服成员。
 */
enum MessageRole: string implements LabeledEnum
{
    case Visitor = 'visitor';
    case Ai = 'ai';
    case Teammate = 'teammate';
    case Tool = 'tool';

    public function label(): string
    {
        return match ($this) {
            self::Visitor => __('conversation.message_roles.visitor'),
            self::Ai => __('conversation.message_roles.ai'),
            self::Teammate => __('conversation.message_roles.teammate'),
            self::Tool => __('conversation.message_roles.tool'),
        };
    }

    /**
     * 判断当前发送角色是否允许指定消息内容类型。
     */
    public function allowsKind(MessageKind $kind): bool
    {
        return match ($this) {
            self::Visitor => in_array($kind, [MessageKind::Text, MessageKind::Image, MessageKind::File], true),
            self::Ai => in_array($kind, [MessageKind::Text, MessageKind::Summary, MessageKind::ToolCall], true),
            self::Teammate => in_array($kind, [MessageKind::Text, MessageKind::Image, MessageKind::File, MessageKind::Summary], true),
            self::Tool => $kind === MessageKind::ToolResult,
        };
    }
}
