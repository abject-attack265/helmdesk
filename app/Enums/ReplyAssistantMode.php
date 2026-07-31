<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 收件箱 AI 回复助手的任务模式，由后端统一下发给前端分段控件。
 */
enum ReplyAssistantMode: string implements LabeledEnum
{
    case Reply = 'reply';
    case Rewrite = 'rewrite';

    public function label(): string
    {
        return match ($this) {
            self::Reply => __('conversation.reply_assistant_modes.reply'),
            self::Rewrite => __('conversation.reply_assistant_modes.rewrite'),
        };
    }
}
