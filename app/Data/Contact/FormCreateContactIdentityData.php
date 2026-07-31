<?php

namespace App\Data\Contact;

use App\Enums\IdentityType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * 创建联系人身份标识表单数据。
 * 来自 resources/js/pages/contacts/Index.vue、Trash.vue 和 ContactDetailDrawer.vue 的新增表单提交，后端用它做校验并写入联系人相关记录。
 */
class FormCreateContactIdentityData extends Data
{
    public function __construct(
        public string $type,
        public string $value,
        public ?string $namespace = null,
    ) {}

    public static function rules(ValidationContext $context): array
    {
        $type = $context->payload['type'] ?? null;

        $valueRules = ['required', 'string', 'max:500'];

        if ($type === IdentityType::Email->value) {
            $valueRules = ['required', 'string', 'email:rfc', 'max:255'];
        }

        if ($type === IdentityType::Phone->value) {
            $valueRules = ['required', 'string', 'max:50', 'regex:/^\+[0-9\s().-]+$/'];
        }

        return [
            'type' => ['required', 'string', Rule::in(array_column(IdentityType::cases(), 'value'))],
            'value' => $valueRules,
            'namespace' => ['nullable', 'string', 'max:255'],
        ];
    }
}
