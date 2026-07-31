<?php

namespace App\Data\KnowledgeBase;

use Spatie\LaravelData\Data;

/**
 * 手动添加知识库文档表单 Data。
 * 来源：resources/js/pages/knowledgeBase/KnowledgeManualDocumentDialog.vue 的「手动添加」模式，
 * 后端按 Markdown 正文落库为 knowledge_documents 表中 source_type=manual 的文档，不写附件。
 */
class FormCreateManualKnowledgeDocumentData extends Data
{
    /**
     * 标题最大长度，超过会让 original_filename 接近 string(255) 上限。
     */
    public const int TITLE_MAX_LENGTH = 200;

    /**
     * 正文最大长度，按字符数限制（数据库底层为 longText，足够，但避免一次写入过大内容）。
     */
    public const int CONTENT_MAX_LENGTH = 200_000;

    public function __construct(
        public string $title,
        public string $content,
        public ?string $group_id = null,
    ) {}

    /**
     * 手动添加文档的校验规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX_LENGTH],
            'content' => ['required', 'string', 'max:'.self::CONTENT_MAX_LENGTH],
            'group_id' => ['nullable', 'string'],
        ];
    }
}
