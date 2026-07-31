<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 知识库文档来源类型。
 * upload 代表用户上传的真实文件，会保留原始附件；manual 代表用户在弹窗中手动录入的 Markdown 内容，
 * 没有原始附件，仅靠模型上的 content 字段承载正文。问答条目使用独立的 knowledge_qa_* 表。
 */
enum KnowledgeDocumentSourceType: string implements LabeledEnum
{
    case Upload = 'upload';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Upload => __('knowledge_base.documents.source_types.upload'),
            self::Manual => __('knowledge_base.documents.source_types.manual'),
        };
    }
}
