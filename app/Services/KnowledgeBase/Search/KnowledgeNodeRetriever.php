<?php

namespace App\Services\KnowledgeBase\Search;

use App\Enums\KnowledgeIndexingStrategy;
use App\Enums\KnowledgeNodeKind;
use App\Models\KnowledgeNode;
use App\Services\KnowledgeBase\KnowledgeVectorTableManager;
use App\Services\Search\TntBooleanQueryCompiler;
use TeamTNT\TNTSearch\TNTSearch;

class KnowledgeNodeRetriever
{
    public function __construct(
        private readonly TntBooleanQueryCompiler $queryCompiler,
        private readonly KnowledgeVectorTableManager $vectorTables,
    ) {}

    public function vectorRetrieve(
        array $knowledgeBaseIds,
        array $queryEmbeddings,
        array $strategyValues,
        int $topK,
        ?string $embeddingModelId = null,
        string $sourceLabel = 'vector',
    ): array {
        if ($knowledgeBaseIds === [] || $queryEmbeddings === [] || $topK <= 0) {
            return [];
        }

        $hits = [];
        foreach ($queryEmbeddings as $queryIndex => $queryEmbedding) {
            $queryVector = array_map(static fn ($value): float => (float) $value, $queryEmbedding);
            $dimension = count($queryVector);
            if ($dimension === 0) {
                continue;
            }

            $allowedQuery = KnowledgeNode::query()
                ->whereIn('knowledge_base_id', $knowledgeBaseIds)
                ->where('embedding_dim', $dimension)
                ->when($strategyValues !== [], fn ($query) => $query->whereIn('strategy', $strategyValues))
                ->when($embeddingModelId !== null && $embeddingModelId !== '', fn ($query) => $query->where('embedding_model_id', $embeddingModelId));

            $allowedIds = $allowedQuery->pluck('id')->map(static fn ($id): string => (string) $id)->all();
            $allowedCount = count($allowedIds);
            if ($allowedCount === 0) {
                continue;
            }

            $allowed = array_fill_keys($allowedIds, true);
            $candidateLimit = max($topK, $topK * 6);

            do {
                $candidates = $this->vectorTables->knnSearch($dimension, $queryVector, $candidateLimit);
                $matched = array_values(array_filter(
                    $candidates,
                    static fn (array $candidate): bool => isset($allowed[(string) $candidate['node_id']]),
                ));

                if (count($matched) >= $topK || count($candidates) < $candidateLimit) {
                    break;
                }

                $candidateLimit *= 2;
            } while (true);

            $nodes = KnowledgeNode::query()
                ->whereIn('id', array_map(static fn (array $candidate): string => (string) $candidate['node_id'], $matched))
                ->get()
                ->keyBy(fn (KnowledgeNode $node): string => (string) $node->id);

            $rank = 0;
            foreach (array_slice($matched, 0, $topK) as $candidate) {
                $node = $nodes->get((string) $candidate['node_id']);
                if ($node === null) {
                    continue;
                }

                $rank++;
                $distance = (float) $candidate['distance'];
                $hits[] = $this->toHit(
                    node: $node,
                    source: $sourceLabel,
                    score: $this->distanceToScore($distance),
                    rank: $rank,
                    metadata: [
                        'query_index' => $queryIndex,
                        'distance' => $distance,
                    ],
                );
            }
        }

        return $hits;
    }

    public function fullTextRetrieve(
        array $knowledgeBaseIds,
        array $queries,
        array $strategyValues,
        int $topK,
        ?string $embeddingModelId = null,
    ): array {
        if ($knowledgeBaseIds === [] || $queries === [] || $topK <= 0) {
            return [];
        }

        $hits = [];
        foreach ($queries as $queryIndex => $query) {
            $compiledQuery = $this->queryCompiler->compile((string) $query);
            if ($compiledQuery === '') {
                continue;
            }

            $constraints = KnowledgeNode::query()->whereIn('knowledge_base_id', $knowledgeBaseIds);
            if ($strategyValues !== []) {
                $constraints->whereIn('strategy', $strategyValues);
            }
            if ($embeddingModelId !== null && $embeddingModelId !== '') {
                $constraints->where('embedding_model_id', $embeddingModelId);
            }

            $nodes = KnowledgeNode::search($compiledQuery, $this->booleanSearchCallback())
                ->constrain($constraints)
                ->get()
                ->take($topK);

            foreach ($nodes as $rank => $node) {
                $hits[] = $this->toHit(
                    node: $node,
                    source: 'fulltext',
                    score: 0.0,
                    rank: $rank + 1,
                    metadata: ['query_index' => $queryIndex],
                );
            }
        }

        return $hits;
    }

    private function booleanSearchCallback(): callable
    {
        return static function (TNTSearch $tnt, string $query): array {
            $result = $tnt->searchBoolean($query, max(1, (int) $tnt->totalDocumentsInCollection()));
            $result['docScores'] = array_fill_keys($result['ids'], 0.0);

            return $result;
        };
    }

    private function toHit(
        KnowledgeNode $node,
        string $source,
        float $score,
        int $rank,
        array $metadata,
    ): KnowledgeSearchHit {
        return new KnowledgeSearchHit(
            source: $source,
            score: $score,
            rank: $rank,
            knowledgeNodeId: (string) $node->id,
            knowledgeBaseId: (string) $node->knowledge_base_id,
            documentId: $node->document_id !== null ? (string) $node->document_id : null,
            qaEntryId: $node->qa_entry_id !== null ? (string) $node->qa_entry_id : null,
            qaQuestionId: $node->qa_question_id !== null ? (string) $node->qa_question_id : null,
            headingPath: $node->heading_path !== null ? (string) $node->heading_path : null,
            byteStart: $node->byte_start !== null ? (int) $node->byte_start : null,
            byteEnd: $node->byte_end !== null ? (int) $node->byte_end : null,
            content: (string) $node->content,
            metadata: [
                'strategy' => $node->strategy instanceof KnowledgeIndexingStrategy
                    ? $node->strategy->value
                    : (string) $node->strategy,
                'kind' => $node->kind instanceof KnowledgeNodeKind
                    ? $node->kind->value
                    : (string) $node->kind,
                'level' => (int) $node->level,
                ...$metadata,
            ],
        );
    }

    private function distanceToScore(float $distance): float
    {
        return 1.0 / (1.0 + max(0.0, $distance));
    }
}
