<?php

namespace App\Services\KnowledgeBase\Search;

/**
 * 单条召回结果的内部表达。
 *
 * 不直接发给 Agent；最终拼装成 KnowledgeSearchResultData 时会再做一次裁剪。
 * 字段语义：
 *  - source：vector / fulltext / raptor，告诉混合融合器该条记录来自哪个 retriever；
 *  - score：原始得分（向量是 distance 反转，FTS 是 bm25 反转），不同 retriever 间不可直接比较，
 *           只在自家 retriever 内部用作排序与 RRF 输入；
 *  - rank：本 retriever 内的从 1 开始排名，给 RRF 直接用；
 *  - knowledgeNodeId / documentId / qaEntryId / qaQuestionId 等保留原始来源，便于 ContextExpander 二次拉取；
 *  - byteStart / byteEnd / headingPath 帮助前端 / Agent 给出可读定位；
 *  - content：用于直接呈现给 Agent 的命中文本，已是 canonical 分段；
 *  - metadata：自由扩展，例如 raptor 的 children_ids 或者 grep 的 match_offset。
 */
final class KnowledgeSearchHit
{
    /**
     * 承载单条召回结果：来源 retriever、原始得分与排名、来源标识及命中文本。
     *
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $source,
        public readonly float $score,
        public readonly int $rank,
        public readonly string $knowledgeNodeId,
        public readonly string $knowledgeBaseId,
        public readonly ?string $documentId,
        public readonly ?string $qaEntryId,
        public readonly ?string $qaQuestionId,
        public readonly ?string $headingPath,
        public readonly ?int $byteStart,
        public readonly ?int $byteEnd,
        public readonly string $content,
        public readonly array $metadata = [],
    ) {}

    /**
     * 复制当前命中，仅覆盖指定字段。
     *
     * 融合 / 重排 / 上下文扩展三道工序都只改 source / score / rank / metadata 这几项，
     * 其余字段沿用当前命中。可覆盖键只有这四个；传入其它键不会被读到。
     *
     * @param  array{
     *     source?: string,
     *     score?: float,
     *     rank?: int,
     *     metadata?: array<string, mixed>,
     * }  $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            source: $overrides['source'] ?? $this->source,
            score: $overrides['score'] ?? $this->score,
            rank: $overrides['rank'] ?? $this->rank,
            knowledgeNodeId: $this->knowledgeNodeId,
            knowledgeBaseId: $this->knowledgeBaseId,
            documentId: $this->documentId,
            qaEntryId: $this->qaEntryId,
            qaQuestionId: $this->qaQuestionId,
            headingPath: $this->headingPath,
            byteStart: $this->byteStart,
            byteEnd: $this->byteEnd,
            content: $this->content,
            metadata: $overrides['metadata'] ?? $this->metadata,
        );
    }
}
