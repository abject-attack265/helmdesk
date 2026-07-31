<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 知识库文档解析阶段状态，用于控制 parsed_content 的生成流程。
 */
enum KnowledgeDocumentParseStatus: string implements LabeledEnum
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('knowledge_base.documents.parse_statuses.pending'),
            self::Processing => __('knowledge_base.documents.parse_statuses.processing'),
            self::Succeeded => __('knowledge_base.documents.parse_statuses.succeeded'),
            self::Failed => __('knowledge_base.documents.parse_statuses.failed'),
            self::Skipped => __('knowledge_base.documents.parse_statuses.skipped'),
        };
    }
}
