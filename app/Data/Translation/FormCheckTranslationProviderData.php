<?php

namespace App\Data\Translation;

use Spatie\LaravelData\Data;

/** 翻译供应商连接测试表单。 */
class FormCheckTranslationProviderData extends Data
{
    /** 创建翻译供应商连接测试数据。 */
    public function __construct(
        public string $text,
        public string $target_lang,
        public ?string $source_lang = null,
    ) {}

    /**
     * 返回连接测试表单校验规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:500'],
            'target_lang' => ['required', 'string', 'max:16'],
            'source_lang' => ['nullable', 'string', 'max:16'],
        ];
    }
}
