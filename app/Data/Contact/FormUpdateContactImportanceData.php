<?php

namespace App\Data\Contact;

use Spatie\LaravelData\Data;

/**
 * 更新重点客户标记表单数据。
 * 来自联系人详情、联系人列表和收件箱的重点客户切换操作。
 */
class FormUpdateContactImportanceData extends Data
{
    public function __construct(
        public bool $is_important,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'is_important' => ['required', 'boolean'],
        ];
    }
}
