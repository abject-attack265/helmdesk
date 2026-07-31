<?php

namespace App\Data\CustomAttribute;

use App\Enums\AttributeType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 创建属性定义表单数据。
 */
class FormCreateAttributeDefinitionData extends Data
{
    public function __construct(
        public string $key,
        public string $name,
        public ?string $description,
        public string $type,
        /** @var array<string, mixed>|null */
        public ?array $config,
        public bool $is_filterable = false,
        public bool $is_ai_readable = false,
        public bool $is_ai_writable = false,
    ) {}

    public static function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_map(fn (AttributeType $t) => $t->value, AttributeType::cases()))],
            'config' => ['nullable', 'array'],
            'is_filterable' => ['boolean'],
            'is_ai_readable' => ['boolean'],
            'is_ai_writable' => ['boolean'],
        ];
    }

    public static function messages(): array
    {
        return [
            'key.regex' => __('custom_attribute.invalid_key_format'),
        ];
    }
}
