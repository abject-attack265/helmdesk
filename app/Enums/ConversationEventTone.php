<?php

namespace App\Enums;

/**
 * 会话事件展示语气，约束前端视觉权重而不暴露具体颜色实现。
 */
enum ConversationEventTone: string
{
    case Normal = 'normal';
    case Important = 'important';
    case Muted = 'muted';
    case Warning = 'warning';
}
