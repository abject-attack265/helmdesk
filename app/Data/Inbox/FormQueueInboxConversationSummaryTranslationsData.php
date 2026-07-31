<?php

namespace App\Data\Inbox;

use App\Enums\ReceptionLanguage;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 收件箱可见会话摘要翻译请求数据。
 */
class FormQueueInboxConversationSummaryTranslationsData extends Data
{
    /**
     * 创建带源语言和目标语言的摘要翻译请求。
     *
     * @param  list<string>  $conversation_ids
     */
    public function __construct(
        public array $conversation_ids,
        public string $target_locale,
        public string $source_locale,
        public bool $force = false,
    ) {}

    /**
     * 返回表单校验规则。
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'conversation_ids' => ['required', 'array', 'max:20'],
            'conversation_ids.*' => ['required', 'string', 'ulid'],
            'force' => ['boolean'],
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
