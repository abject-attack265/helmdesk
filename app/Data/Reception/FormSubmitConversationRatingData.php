<?php

namespace App\Data\Reception;

use App\Enums\ConversationRatingScore;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 访客提交会话满意度评价的表单数据。
 * 提交来源：访客端 StandaloneCanvas 评价卡 → POST /api/chat/{code}/rating。
 */
class FormSubmitConversationRatingData extends Data
{
    public function __construct(
        public ConversationRatingScore $score,
        public ?string $comment = null,
    ) {}

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'score' => ['required', Rule::enum(ConversationRatingScore::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
