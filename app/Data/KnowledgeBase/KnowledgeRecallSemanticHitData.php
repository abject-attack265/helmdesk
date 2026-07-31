<?php

namespace App\Data\KnowledgeBase;

use Spatie\LaravelData\Data;

/**
 * 知识库测试页面中按意思找到的一条内容。
 */
class KnowledgeRecallSemanticHitData extends Data
{
    /**
     * 封装语义命中的来源、得分和内容。
     */
    public function __construct(
        public int $rank,
        public string $source,
        public string $source_label,
        public float $score,
        public string $origin_type,
        public ?string $origin_title,
        public ?string $heading_path,
        public string $content,
    ) {}

    /**
     * 补充内容来源标题和展示标签。
     *
     * @param  array<string, string>  $documentTitles  document_id => 文件名
     * @param  array<string, string>  $qaQuestions  qa_entry_id => 主问题
     */
    public static function fromHit(KnowledgeSearchSemanticHitData $hit, array $documentTitles, array $qaQuestions): self
    {
        if ($hit->qa_entry_id !== null) {
            $originType = 'qa';
            $originTitle = $qaQuestions[$hit->qa_entry_id] ?? null;
        } else {
            $originType = 'document';
            $originTitle = $hit->document_id !== null ? ($documentTitles[$hit->document_id] ?? null) : null;
        }

        return new self(
            rank: $hit->rank,
            source: $hit->source,
            source_label: self::resolveSourceLabel($hit->source),
            score: $hit->score,
            origin_type: $originType,
            origin_title: $originTitle,
            heading_path: $hit->heading_path,
            content: $hit->content,
        );
    }

    /**
     * 返回内容来源的展示标签。
     */
    private static function resolveSourceLabel(string $source): string
    {
        return match ($source) {
            'vector' => __('knowledge_recall.sources.vector'),
            'fulltext' => __('knowledge_recall.sources.fulltext'),
            'raptor' => __('knowledge_recall.sources.raptor'),
            'hybrid' => __('knowledge_recall.sources.hybrid'),
        };
    }
}
