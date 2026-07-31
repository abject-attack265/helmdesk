<?php

namespace App\Enums;

/**
 * AI 助手线程中的内部对话角色。
 */
enum AiAssistantMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
