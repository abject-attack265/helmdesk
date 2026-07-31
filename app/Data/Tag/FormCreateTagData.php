<?php

namespace App\Data\Tag;

use Spatie\LaravelData\Data;

/**
 * 创建标签表单数据。
 * 提交来源：标签管理页 resources/js/pages/tags/Index.vue 的「新建标签」对话框。
 * 标签必属于一个标签组（tag_group_id），适用维度由所属组继承。
 */
class FormCreateTagData extends Data
{
    public function __construct(
        public string $tag_group_id,
        public string $name,
        public ?string $color = null,
        public ?string $description = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'tag_group_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:50', 'regex:/\S/'],
            'color' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
