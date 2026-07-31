<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 访客对会话的满意度评分。本期为二元赞/踩，前端渲染 👍/👎，指标侧「满意度 = 好评占比」。
 */
enum ConversationRatingScore: string implements LabeledEnum
{
    case Positive = 'positive';
    case Negative = 'negative';

    public function label(): string
    {
        return match ($this) {
            self::Positive => __('conversation.rating.scores.positive'),
            self::Negative => __('conversation.rating.scores.negative'),
        };
    }
}
