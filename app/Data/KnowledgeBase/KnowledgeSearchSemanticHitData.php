<?php

namespace App\Data\KnowledgeBase;

use App\Services\KnowledgeBase\Search\KnowledgeSearchHit;
use Spatie\LaravelData\Data;

/**
 * Agent 知识库检索响应里的单条语义命中（KnowledgeSearchResultData::$semantic_hits 的元素）。
 *
 * 由服务层 KnowledgeSearchHit 在检索流水线出口处转换而来，是命中跨出服务层后的传输形态：
 * Agent 工具直接序列化下发，召回测试面板（RunKnowledgeRecallTestAction）按类型化字段二次富集。
 */
class KnowledgeSearchSemanticHitData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $source,
        public float $score,
        public int $rank,
        public string $node_id,
        public string $knowledge_base_id,
        public ?string $document_id,
        public ?string $qa_entry_id,
        public ?string $qa_question_id,
        public ?string $heading_path,
        public ?int $byte_start,
        public ?int $byte_end,
        public string $content,
        public array $metadata = [],
    ) {}

    /**
     * 由服务层命中构造传输 Data。
     */
    public static function fromHit(KnowledgeSearchHit $hit): self
    {
        return new self(
            source: $hit->source,
            score: $hit->score,
            rank: $hit->rank,
            node_id: $hit->knowledgeNodeId,
            knowledge_base_id: $hit->knowledgeBaseId,
            document_id: $hit->documentId,
            qa_entry_id: $hit->qaEntryId,
            qa_question_id: $hit->qaQuestionId,
            heading_path: $hit->headingPath,
            byte_start: $hit->byteStart,
            byte_end: $hit->byteEnd,
            content: $hit->content,
            metadata: $hit->metadata,
        );
    }
}
