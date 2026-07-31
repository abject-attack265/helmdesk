<?php

namespace App\Data\Inbox;

use App\Enums\ReceptionLanguage;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 收件箱会话列表预览翻译请求数据。
 */
class FormTranslateInboxConversationPreviewsData extends Data
{
    /**
     * 创建带源语言和目标语言的会话预览翻译请求。
     *
     * @param  list<string>  $conversation_ids
     */
    public function __construct(
        public array $conversation_ids,
        public string $target_locale,
        public string $source_locale,
    ) {}

    /**
     * 返回会话预览翻译请求的验证规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'conversation_ids' => ['required', 'array', 'min:1', 'max:50'],
            'conversation_ids.*' => ['required', 'string', 'distinct'],
            'target_locale' => ['required', Rule::enum(ReceptionLanguage::class)],
            'source_locale' => ['required', Rule::in([
                'auto',
                ...array_map(
                    static fn (ReceptionLanguage $language): string => $language->value,
                    ReceptionLanguage::cases(),
                ),
            ])],
        ];
    }
}
