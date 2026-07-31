<?php

namespace App\Services\KnowledgeBase\Search;

use App\Services\KnowledgeBase\KnowledgeEmbeddingService;
use App\Services\KnowledgeBase\KnowledgeEngine;
use Illuminate\Support\Facades\Log;

/**
 * 查询侧嵌入器：把 Agent 给到的多条 query 一次性嵌入。
 *
 * 与索引侧嵌入逻辑共享同一个 KnowledgeEmbeddingService（最终经 NeuronAI 直调），
 * 但在这一层做几个使用层的补强：
 *  - pin 的全局向量模型没有可用备份时，直接返回 [0, []]，让召回器自然退化为不检索；
 *  - 入参做 trim + 去空过滤，避免空 query 产生多余的嵌入批次（去重已由入口
 *    FormKnowledgeSearchData::normalizedQueries() 保证）；
 *  - 返回的向量与过滤后的 query 顺序一一对应；
 *  - 全局引擎 pin 了 embedding_dimension 时把它作为可信源，运行时 embed 返回不同维度
 *    意味着模型 API 已经偏离落库时的版本，此时退化为 [0, []]，让召回器退到全文检索，
 *    避免拿新维度向量去比对旧维度的库。
 */
class KnowledgeQueryEmbedder
{
    public function __construct(
        private readonly KnowledgeEmbeddingService $embedder,
        private readonly KnowledgeEngine $engine,
    ) {}

    /**
     * 把 app 维度的多个 query 一次性嵌入。
     *
     * @param  list<string>  $queries
     * @return array{0: int, 1: list<list<float>>} [dimension, vectors]; queries 为空或模型缺失时返回 [0, []]
     */
    public function embed(array $queries): array
    {
        // 嵌入模型取该应用生效的向量模型（同规格备份打散后首个），无可用备份时退化为不走向量检索。
        $engine = $this->engine->current();
        $model = $engine->pinnedEmbeddingModel();
        if ($model === null || $model->provider === null) {
            return [0, []];
        }

        $cleaned = [];
        foreach ($queries as $query) {
            $trimmed = trim((string) $query);
            if ($trimmed === '') {
                continue;
            }
            $cleaned[] = $trimmed;
        }
        if ($cleaned === []) {
            return [0, []];
        }

        [$dimension, $vectors] = $this->embedder->embedTexts($model, $cleaned, $engine->embeddingDimension());

        $expected = $engine->embeddingDimension();
        if ($expected !== null && $expected > 0 && $dimension !== $expected) {
            Log::warning('Knowledge query embedding dimension mismatch; falling back to non-vector retrieval.', [
                'expected_dimension' => $expected,
                'actual_dimension' => $dimension,
            ]);

            return [0, []];
        }

        return [$dimension, array_values($vectors)];
    }
}
