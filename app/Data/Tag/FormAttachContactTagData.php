<?php

namespace App\Data\Tag;

use Spatie\LaravelData\Data;

/**
 * 绑定联系人标签表单数据。
 * 来自 resources/js/pages/tags/Index.vue 的操作表单或弹窗提交，后端用它校验本次绑定动作。
 */
class FormAttachContactTagData extends Data
{
    public function __construct(
        public string $tag_id,
    ) {}

    public static function rules(): array
    {
        return [
            'tag_id' => ['required', 'string'],
        ];
    }
}
