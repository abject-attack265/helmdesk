<?php

namespace App\Data\CustomAttribute;

use Spatie\LaravelData\Data;

/**
 * 更新属性定义表单数据。
 * 来自 resources/js/pages/appSettings/datas/Attribute.vue 的编辑表单提交，后端用它校验并保存自定义属性配置。
 */
class FormUpdateAttributeDefinitionData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        /** @var array<string, mixed>|null */
        public ?array $config,
        public bool $is_filterable = false,
        public bool $is_ai_readable = false,
        public bool $is_ai_writable = false,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
            'is_filterable' => ['boolean'],
            'is_ai_readable' => ['boolean'],
            'is_ai_writable' => ['boolean'],
        ];
    }
}
