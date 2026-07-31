<?php

namespace App\Data\Contact;

use Spatie\LaravelData\Data;

/**
 * 替换联系人身份标识表单数据。
 * 来自 resources/js/pages/contacts/Index.vue、Trash.vue 和 ContactDetailDrawer.vue 的操作表单或弹窗提交，后端用它校验本次替换动作。
 */
class FormReplaceContactIdentityData extends Data
{
    public function __construct(
        public string $value,
    ) {}

    public static function rules(): array
    {
        return [
            'value' => ['required', 'string', 'max:500'],
        ];
    }
}
