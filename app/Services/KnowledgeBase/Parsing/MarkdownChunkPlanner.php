<?php

namespace App\Services\KnowledgeBase\Parsing;

use App\Enums\KnowledgeChunkingStrategy;
use App\Exceptions\BusinessException;
use App\Services\KnowledgeBase\BoundKnowledgeEngine;
use App\Services\KnowledgeBase\KnowledgeEmbeddingService;
use App\Services\KnowledgeBase\KnowledgeEngine;

/**
 * Markdown 分段编排：按应用生效的知识库引擎配置统一产出"可索引段"。
 *
 * - fixed 策略：纯结构化分段（段落级、累加到 max_tokens、按 overlap 软回滚）。
 * - semantic 策略：先用 sentenceUnits 拆句、调嵌入模型，再按余弦相似度合并相邻句子。
 *
 * 任何上游（Vector / Raptor / FullText）调用即可获得"统一形状"的分段列表，
 * 避免每个索引器各自硬编码 chunk 尺寸或丢失 heading_path。
 *
 * @phpstan-type PlannedSegment array{
 *     content: string,
 *     heading_path: list<string>,
 *     byte_start: int,
 *     byte_end: int,
 *     token_count: int,
 * }
 */
class MarkdownChunkPlanner
{
    /**
     * 相邻句子余弦相似度低于该阈值即在此处切段。
     */
    private const float SEMANTIC_SIMILARITY_THRESHOLD = 0.72;

    public function __construct(
        private readonly MarkdownChunker $chunker,
        private readonly KnowledgeEmbeddingService $embedder,
        private readonly KnowledgeEngine $engine,
    ) {}

    /**
     * 按应用生效的知识库引擎配置切分 markdown，统一返回带 heading_path 的段列表。
     *
     * @return list<PlannedSegment>
     */
    public function plan(string $markdown): array
    {
        $engine = $this->engine->current();
        $maxTokens = max(1, $engine->chunkMaxTokens());
        $overlapTokens = max(0, $engine->chunkOverlapTokens());

        if ($engine->chunkingStrategy() === KnowledgeChunkingStrategy::Semantic) {
            return $this->planSemantic($engine, $markdown, $maxTokens);
        }

        return $this->chunker->chunk($markdown, $maxTokens, $overlapTokens)['segments'];
    }

    /**
     * semantic 策略：以句子为最小单元，按句间相似度向后累积，超过窗口或语义跳变即落段。
     *
     * @return list<PlannedSegment>
     */
    private function planSemantic(BoundKnowledgeEngine $engine, string $markdown, int $maxTokens): array
    {
        $units = $this->chunker->sentenceUnits($markdown);
        if ($units === []) {
            return [];
        }

        // 嵌入模型取该应用生效的向量模型（同规格备份打散后首个），取不到直接报错。
        $embeddingModel = $engine->pinnedEmbeddingModel();
        if ($embeddingModel === null || $embeddingModel->provider === null) {
            throw new BusinessException(__('knowledge_base.messages.invalid_embedding_model'));
        }

        $sentences = array_map(static fn (array $unit): string => (string) $unit['content'], $units);
        [, $vectors] = $this->embedder->embedTexts($embeddingModel, $sentences, $engine->embeddingDimension());

        $segments = [];
        $bufferUnits = [];
        $bufferVectors = [];
        $bufferTokens = 0;

        $flush = function () use (&$segments, &$bufferUnits, &$bufferVectors, &$bufferTokens): void {
            if ($bufferUnits === []) {
                return;
            }
            $first = array_first($bufferUnits);
            $last = array_last($bufferUnits);
            $segments[] = [
                'content' => implode(' ', array_map(
                    static fn (array $unit): string => (string) $unit['content'],
                    $bufferUnits,
                )),
                'heading_path' => $first['heading_path'],
                'byte_start' => (int) $first['byte_start'],
                'byte_end' => (int) $last['byte_end'],
                'token_count' => $bufferTokens,
            ];
            $bufferUnits = [];
            $bufferVectors = [];
            $bufferTokens = 0;
        };

        foreach ($units as $index => $unit) {
            $unitTokens = (int) $unit['token_count'];
            $vector = $vectors[$index] ?? [];

            if ($bufferUnits !== []) {
                $similarity = $this->cosineSimilarity($this->averageVector($bufferVectors), $vector);
                if ($bufferTokens + $unitTokens > $maxTokens || $similarity < self::SEMANTIC_SIMILARITY_THRESHOLD) {
                    $flush();
                }
            }

            $bufferUnits[] = $unit;
            $bufferVectors[] = $vector;
            $bufferTokens += $unitTokens;
        }

        $flush();

        return $segments;
    }

    /**
     * 把段的 heading_path 数组压成"A › B › C"形式，留给写入仓库做展示。
     */
    public function joinHeadingPath(mixed $headingPath): ?string
    {
        if (! is_array($headingPath) || $headingPath === []) {
            return null;
        }
        $clean = array_values(array_filter(array_map(
            static fn ($p) => is_string($p) ? trim($p) : '',
            $headingPath,
        ), static fn (string $p) => $p !== ''));

        return $clean === [] ? null : implode(' › ', $clean);
    }

    /**
     * 逐维求当前缓冲区句向量的均值，作为已累积段的语义中心。
     *
     * @param  list<list<float>>  $vectors
     * @return list<float>
     */
    private function averageVector(array $vectors): array
    {
        $count = count($vectors);
        if ($count === 0) {
            return [];
        }

        $sum = [];
        foreach ($vectors as $vector) {
            foreach ($vector as $index => $value) {
                $sum[$index] = ($sum[$index] ?? 0.0) + (float) $value;
            }
        }

        return array_map(static fn (float $value): float => $value / $count, $sum);
    }

    /**
     * 计算两个向量的余弦相似度，用于判断相邻句子是否语义连贯；任一向量为零向量时返回 0。
     *
     * @param  list<float>  $left
     * @param  list<float>  $right
     */
    private function cosineSimilarity(array $left, array $right): float
    {
        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;
        $length = min(count($left), count($right));

        for ($index = 0; $index < $length; $index++) {
            $leftValue = (float) $left[$index];
            $rightValue = (float) $right[$index];
            $dot += $leftValue * $rightValue;
            $leftNorm += $leftValue * $leftValue;
            $rightNorm += $rightValue * $rightValue;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }
}
