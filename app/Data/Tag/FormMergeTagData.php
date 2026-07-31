<?php

namespace App\Data\Tag;

use Spatie\LaravelData\Data;

/**
 * 合并标签表单数据。
 * 来自 resources/js/pages/tags/Index.vue 的操作表单或弹窗提交，后端用它校验本次合并动作。
 */
class FormMergeTagData extends Data
{
    public function __construct(
        public string $target_tag_id,
        public string $merged_tag_id,
    ) {}

    public static function rules(): array
    {
        return [
            'target_tag_id' => ['required', 'string'],
            'merged_tag_id' => ['required', 'string', 'different:target_tag_id'],
        ];
    }
}
