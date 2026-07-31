<?php

namespace App\Data\Contact;

use App\Enums\ContactType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 更新联系人表单数据。
 * 来自 resources/js/pages/contacts/Index.vue、Trash.vue 和 ContactDetailDrawer.vue 的编辑表单提交，后端用它校验并保存联系人配置。
 */
class FormUpdateContactData extends Data
{
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public ?string $note = null,
        public ?string $country = null,
        public ?string $city = null,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(array_column(ContactType::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:10000'],
            'country' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
        ];
    }
}
