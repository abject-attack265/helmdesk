<?php

namespace App\Data\KnowledgeBase;

use Spatie\LaravelData\Data;

/**
 * 编辑手动添加的知识库文档表单 Data。
 * 来源：resources/js/pages/knowledgeBase/KnowledgeManualDocumentDialog.vue 的编辑模式，
 * 只更新标题与正文；分组变更走独立的「移动分组」操作。
 */
class FormUpdateManualKnowledgeDocumentData extends Data
{
    public function __construct(
        public string $title,
        public string $content,
    ) {}

    /**
     * 编辑手动文档的校验规则，长度上限与创建保持一致。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.FormCreateManualKnowledgeDocumentData::TITLE_MAX_LENGTH],
            'content' => ['required', 'string', 'max:'.FormCreateManualKnowledgeDocumentData::CONTENT_MAX_LENGTH],
        ];
    }
}
