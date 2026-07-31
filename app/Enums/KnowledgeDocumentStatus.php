<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 知识库文档的综合状态，由各阶段状态派生：
 *  - Pending:    刚上传/手动创建，尚未进入解析。
 *  - Parsing:    解析中。
 *  - Parsed:     解析完成但还未启动任一索引策略。
 *  - Indexing:   至少一条已启用的索引策略仍在 pending/processing。
 *  - Indexed:    所有已启用的索引策略都成功。
 *  - Failed:     任意阶段失败（解析失败 或 启用的索引策略失败）。
 *
 * 列表统一把 Parsing、Parsed、Indexing 显示为“处理中”，内部状态仍用于任务流转。
 */
enum KnowledgeDocumentStatus: string implements LabeledEnum
{
    case Pending = 'pending';
    case Parsing = 'parsing';
    case Parsed = 'parsed';
    case Indexing = 'indexing';
    case Indexed = 'indexed';
    case Failed = 'failed';

    /**
     * 返回文档列表使用的状态文案。
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => __('knowledge_base.documents.statuses.pending'),
            self::Parsing => __('knowledge_base.documents.statuses.parsing'),
            self::Parsed => __('knowledge_base.documents.statuses.parsed'),
            self::Indexing => __('knowledge_base.documents.statuses.indexing'),
            self::Indexed => __('knowledge_base.documents.statuses.indexed'),
            self::Failed => __('knowledge_base.documents.statuses.failed'),
        };
    }
}
