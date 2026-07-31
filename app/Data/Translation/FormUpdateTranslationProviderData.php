<?php

namespace App\Data\Translation;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/** 翻译供应商更新表单。 */
class FormUpdateTranslationProviderData extends Data
{
    /** 创建翻译供应商更新表单数据。 */
    public function __construct(
        public string $name,
        public bool $is_active = true,
        /** @var array<string, mixed> */
        public array $configuration = [],
    ) {}

    /**
     * 返回翻译供应商更新表单校验规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128', Rule::unique('translation_providers', 'name')->ignore(request()->route('id'))],
            'is_active' => ['boolean'],
            'configuration' => ['nullable', 'array'],
            'configuration.*' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
