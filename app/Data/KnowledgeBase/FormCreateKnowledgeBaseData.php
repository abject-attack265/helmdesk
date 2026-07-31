<?php

namespace App\Data\KnowledgeBase;

use App\Enums\KnowledgeBaseCategory;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 创建知识库表单数据。
 * 来自 resources/js/pages/knowledgeBase/Create.vue 的新增表单提交，后端用它校验并写入知识库记录。
 */
class FormCreateKnowledgeBaseData extends Data
{
    /**
     * 创建知识库表单字段。
     */
    public function __construct(
        public string $name,
        public ?string $avatar_id,
        public ?string $description,
        public KnowledgeBaseCategory $category,
    ) {}

    /**
     * 返回创建知识库表单校验规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'avatar_id' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::enum(KnowledgeBaseCategory::class)],
        ];
    }
}
