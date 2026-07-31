<?php

namespace App\Data\Inbox;

use App\Enums\ReceptionLanguage;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 收件箱可见消息翻译请求数据。
 */
class FormQueueInboxMessageTranslationsData extends Data
{
    /**
     * 创建带源语言、目标语言和翻译范围的消息翻译请求。
     *
     * @param  list<string>  $message_ids
     */
    public function __construct(
        public array $message_ids,
        public string $target_locale,
        public string $source_locale,
        public bool $force = false,
    ) {}

    /**
     * 返回可见消息翻译请求的验证规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'message_ids' => ['required', 'array', 'min:1', 'max:20'],
            'message_ids.*' => ['required', 'string', 'distinct'],
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
