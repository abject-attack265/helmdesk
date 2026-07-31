<?php

namespace App\Data\KnowledgeBase;

use App\Services\KnowledgeBase\Search\GrepMatch;
use Spatie\LaravelData\Data;

/**
 * Agent 知识库检索响应里的单条 grep 字面命中（KnowledgeSearchResultData::$grep_matches 的元素）。
 *
 * 由服务层 GrepMatch 在检索流水线出口处转换而来，带行号 / 列号 / byte 偏移 / 上下文回显，
 * 供 Agent 精确定位与召回测试面板（RunKnowledgeRecallTestAction）二次富集。
 */
class KnowledgeSearchGrepMatchData extends Data
{
    public function __construct(
        public string $knowledge_base_id,
        public ?string $document_id,
        public ?string $document_title,
        public ?string $qa_entry_id,
        public ?string $qa_question_id,
        public ?string $qa_answer_id,
        public string $field,
        public string $query,
        public int $line,
        public int $column,
        public int $byte_start,
        public int $byte_end,
        public string $match,
        public string $context_before,
        public string $context_after,
        public ?string $heading_path,
    ) {}

    /**
     * 由服务层 grep 命中构造传输 Data。
     */
    public static function fromMatch(GrepMatch $match): self
    {
        return new self(
            knowledge_base_id: $match->knowledgeBaseId,
            document_id: $match->documentId,
            document_title: $match->documentTitle,
            qa_entry_id: $match->qaEntryId,
            qa_question_id: $match->qaQuestionId,
            qa_answer_id: $match->qaAnswerId,
            field: $match->field,
            query: $match->query,
            line: $match->line,
            column: $match->column,
            byte_start: $match->byteStart,
            byte_end: $match->byteEnd,
            match: $match->match,
            context_before: $match->contextBefore,
            context_after: $match->contextAfter,
            heading_path: $match->headingPath,
        );
    }
}
