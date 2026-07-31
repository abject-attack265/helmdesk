<?php

namespace App\Data\Tag;

use Spatie\LaravelData\Data;

/**
 * 更新标签组表单数据。
 * 提交来源：标签管理页 resources/js/pages/tags/Index.vue 的「编辑标签组」对话框。
 * 仅允许改名；scope 创建后不可更改，避免组内标签维度漂移。
 */
class FormUpdateTagGroupData extends Data
{
    public function __construct(
        public string $name,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'regex:/\S/'],
        ];
    }
}
