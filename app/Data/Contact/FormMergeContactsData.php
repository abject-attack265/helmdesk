<?php

namespace App\Data\Contact;

use Spatie\LaravelData\Data;

/**
 * 联系人列表合并对话框的提交数据。
 */
class FormMergeContactsData extends Data
{
    /**
     * 接收合并双方的联系人标识。
     */
    public function __construct(
        public string $target_contact_id,
        public string $merged_contact_id,
    ) {}

    /**
     * 校验合并双方均已选择且不是同一联系人。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'target_contact_id' => ['required', 'string'],
            'merged_contact_id' => ['required', 'string', 'different:target_contact_id'],
        ];
    }
}
