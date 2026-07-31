<?php

namespace App\Enums;

/**
 * 会话事件展示语义类型，前端据此选择图标和低干扰样式。
 */
enum ConversationEventSemanticType: string
{
    case Conversation = 'conversation';
    case BotAction = 'bot_action';
    case UserAction = 'user_action';
    case ToolCall = 'tool_call';
    case StatusChange = 'status_change';
    case Warning = 'warning';
}
