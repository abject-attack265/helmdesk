<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 问答条目的索引生命周期状态，列表筛选和状态 badge 使用。
 */
enum KnowledgeQaEntryStatus: string implements LabeledEnum
{
    case Pending = 'pending';
    case Indexing = 'indexing';
    case Indexed = 'indexed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('knowledge_base.qa.statuses.pending'),
            self::Indexing => __('knowledge_base.qa.statuses.indexing'),
            self::Indexed => __('knowledge_base.qa.statuses.indexed'),
            self::Failed => __('knowledge_base.qa.statuses.failed'),
        };
    }
}
