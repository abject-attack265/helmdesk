<?php

namespace App\Data\Contact;

use Spatie\LaravelData\Data;

/**
 * 创建联系人表单数据。
 * 来自 resources/js/pages/contacts/Index.vue、Trash.vue 和 ContactDetailDrawer.vue 的新增表单提交，后端用它做校验并写入联系人相关记录。
 */
class FormCreateContactData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^\+[0-9\s().-]+$/'],
        ];
    }
}
