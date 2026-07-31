<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 被评价会话的处理归属，评价提交时快照。用于报表拆分「AI 满意度」与「人工满意度」。
 *
 * 判定：会话曾被坐席处理过（有 assigned_user_id 或出现过 teammate 消息）记为 Human，否则 Ai。
 */
enum ConversationRatingHandledBy: string implements LabeledEnum
{
    case Ai = 'ai';
    case Human = 'human';

    public function label(): string
    {
        return match ($this) {
            self::Ai => __('conversation.rating.handled_by.ai'),
            self::Human => __('conversation.rating.handled_by.human'),
        };
    }
}
