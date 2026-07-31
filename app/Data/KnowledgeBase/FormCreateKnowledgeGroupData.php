<?php

namespace App\Data\KnowledgeBase;

use Spatie\LaravelData\Data;

/**
 * 创建知识库分组表单提交数据。
 * 由 KnowledgeGroupFormDialog.vue 提交，经 CreateKnowledgeGroupAction 校验并保存。
 */
class FormCreateKnowledgeGroupData extends Data
{
    public function __construct(
        public string $name,
        public ?string $parent_id,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'parent_id' => ['nullable', 'string'],
        ];
    }
}
