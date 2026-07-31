<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 知识库文档索引阶段状态，用于列表展示和流水线调度。
 */
enum KnowledgeDocumentIndexingStatus: string implements LabeledEnum
{
    case Idle = 'idle';
    case Pending = 'pending';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Idle => __('knowledge_base.documents.indexing_statuses.idle'),
            self::Pending => __('knowledge_base.documents.indexing_statuses.pending'),
            self::Processing => __('knowledge_base.documents.indexing_statuses.processing'),
            self::Succeeded => __('knowledge_base.documents.indexing_statuses.succeeded'),
            self::Failed => __('knowledge_base.documents.indexing_statuses.failed'),
        };
    }
}
