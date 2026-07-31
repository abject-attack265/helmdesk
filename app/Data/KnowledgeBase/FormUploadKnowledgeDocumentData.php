<?php

namespace App\Data\KnowledgeBase;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

/**
 * 上传知识库文档表单 Data。
 * 来源：resources/js/pages/knowledgeBase/KnowledgeDocumentUploadPanel.vue 的批量上传面板，
 * 后端按文件落库写入 knowledge_documents 表，并将原文件保存在对象存储中。
 */
class FormUploadKnowledgeDocumentData extends Data
{
    /**
     * 后端允许上传的文档扩展名。
     */
    public const array ALLOWED_EXTENSIONS = [
        'md',
        'markdown',
        'txt',
        'pdf',
        'docx',
        'html',
        'htm',
    ];

    /**
     * @param  array<int, UploadedFile>  $files  本次提交的所有文档文件
     */
    public function __construct(
        public array $files,
        public ?string $group_id = null,
    ) {}

    /**
     * 上传文档校验规则。单个文件不超过 20MB，单次最多 20 个，仅校验扩展名（不强约束 mime type）。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:20480', 'extensions:'.implode(',', self::ALLOWED_EXTENSIONS)],
            'group_id' => ['nullable', 'string'],
        ];
    }
}
