<?php

namespace App\Data\AiProvider;

use Spatie\LaravelData\Data;

/**
 * 更新AI 供应商名称与凭据的表单数据。
 *
 * 来自 resources/js/pages/appSettings/aiProvider/List.vue 凭据区的逐字段提交；
 * 编辑表单回显全部凭据字段，提交空值即清空对应字段。
 */
class FormUpdateAiProviderCredentialsData extends Data
{
    public function __construct(
        public string $name,
        /** @var array<string, mixed> */
        public array $configuration = [],
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'configuration' => ['nullable', 'array'],
            'configuration.*' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
