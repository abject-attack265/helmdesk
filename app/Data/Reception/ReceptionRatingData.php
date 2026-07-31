<?php

namespace App\Data\Reception;

use App\Enums\ConversationRatingScore;
use App\Models\ConversationRating;
use Spatie\LaravelData\Data;

/**
 * 访客端已提交的会话评价快照。
 * 由 ReceptionStateBuilder 组装进 ReceptionStateData，供 StandaloneCanvas 评价卡显示「已评价」态。
 */
class ReceptionRatingData extends Data
{
    public function __construct(
        public ConversationRatingScore $score,
        public ?string $comment,
    ) {}

    public static function fromModel(ConversationRating $rating): self
    {
        return new self(
            score: $rating->score,
            comment: $rating->comment,
        );
    }
}
