<?php

namespace App\Data\KnowledgeBase;

use Spatie\LaravelData\Data;

/**
 * 移动知识库文档到另一个真实分组的表单数据。
 */
class FormMoveKnowledgeDocumentData extends Data
{
    public function __construct(
        public string $group_id,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'group_id' => ['required', 'string'],
        ];
    }
}
