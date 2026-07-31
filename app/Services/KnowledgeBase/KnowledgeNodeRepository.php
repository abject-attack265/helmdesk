<?php

namespace App\Services\KnowledgeBase;

use App\Enums\KnowledgeIndexingStrategy;
use App\Enums\KnowledgeNodeKind;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeNode;
use App\Models\KnowledgeQaEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KnowledgeNodeRepository
{
    public function __construct(
        private readonly KnowledgeEngine $engine,
        private readonly KnowledgeVectorTableManager $vectorTables,
    ) {}

    public function purgeStrategyForDocument(KnowledgeDocument $document, KnowledgeIndexingStrategy $strategy): void
    {
        if ($strategy === KnowledgeIndexingStrategy::Vector) {
            $this->purgeTextVectorsWhere(['document_id' => (string) $document->id]);

            return;
        }

        $this->purgeNodesWhere(['document_id' => (string) $document->id], $strategy);
    }

    public function purgeStrategyForQaEntry(KnowledgeQaEntry $entry, KnowledgeIndexingStrategy $strategy): void
    {
        if ($strategy === KnowledgeIndexingStrategy::Vector) {
            $this->purgeTextVectorsWhere(['qa_entry_id' => (string) $entry->id]);

            return;
        }

        $this->purgeNodesWhere(['qa_entry_id' => (string) $entry->id], $strategy);
    }

    public function purgeAllForDocument(KnowledgeDocument $document): void
    {
        $this->purgeNodesWhere(['document_id' => (string) $document->id], null);
    }

    public function purgeAllForQaEntry(KnowledgeQaEntry $entry): void
    {
        $this->purgeNodesWhere(['qa_entry_id' => (string) $entry->id], null);
    }

    public function purgeAllForKnowledgeBase(KnowledgeBase $knowledgeBase): void
    {
        $this->purgeNodesWhere(['knowledge_base_id' => (string) $knowledgeBase->id], null);
    }

    private function purgeNodesWhere(array $where, ?KnowledgeIndexingStrategy $strategy): void
    {
        $query = KnowledgeNode::query()->where($where);
        if ($strategy !== null) {
            $query->where('strategy', $strategy);
        }

        $nodes = $query->get(['id', 'embedding_dim']);
        if ($nodes->isEmpty()) {
            return;
        }

        $idsByDimension = [];
        foreach ($nodes as $node) {
            $dimension = (int) $node->embedding_dim;
            if ($dimension > 0) {
                $idsByDimension[$dimension][] = (string) $node->id;
            }
        }

        $nodes->unsearchableSync();

        DB::connection('sqlite_rag')->transaction(function () use ($where, $strategy, $idsByDimension): void {
            foreach ($idsByDimension as $dimension => $nodeIds) {
                $this->vectorTables->deleteVectors($dimension, $nodeIds);
            }

            $deleteQuery = KnowledgeNode::query()->where($where);
            if ($strategy !== null) {
                $deleteQuery->where('strategy', $strategy);
            }
            $deleteQuery->delete();
        });
    }

    private function purgeTextVectorsWhere(array $where): void
    {
        $nodes = KnowledgeNode::query()
            ->where($where)
            ->where('strategy', KnowledgeIndexingStrategy::Text)
            ->where('embedding_dim', '>', 0)
            ->get(['id', 'embedding_dim']);

        if ($nodes->isEmpty()) {
            return;
        }

        $idsByDimension = [];
        foreach ($nodes as $node) {
            $idsByDimension[(int) $node->embedding_dim][] = (string) $node->id;
        }

        DB::connection('sqlite_rag')->transaction(function () use ($where, $idsByDimension): void {
            foreach ($idsByDimension as $dimension => $nodeIds) {
                $this->vectorTables->deleteVectors($dimension, $nodeIds);
            }

            KnowledgeNode::query()
                ->where($where)
                ->where('strategy', KnowledgeIndexingStrategy::Text)
                ->where('embedding_dim', '>', 0)
                ->update([
                    'embedding_dim' => 0,
                    'embedding_model_id' => null,
                    'updated_at' => now(),
                ]);
        });
    }

    public function writeCanonicalSegments(
        KnowledgeBase $knowledgeBase,
        KnowledgeDocument $document,
        array $segments,
    ): array {
        if ($segments === []) {
            return [];
        }

        $now = now();
        $rows = [];
        $ids = [];

        foreach ($segments as $segment) {
            $id = (string) Str::ulid();
            $ids[] = $id;
            $rows[] = [
                'id' => $id,
                'knowledge_base_id' => (string) $knowledgeBase->id,
                'document_id' => (string) $document->id,
                'qa_entry_id' => null,
                'qa_question_id' => null,
                'parent_id' => null,
                'strategy' => KnowledgeIndexingStrategy::Text,
                'level' => 0,
                'kind' => KnowledgeNodeKind::Segment,
                'content' => (string) $segment['content'],
                'content_format' => (string) ($segment['content_format'] ?? 'markdown'),
                'heading_path' => $segment['heading_path'] ?? null,
                'byte_start' => $segment['byte_start'] ?? null,
                'byte_end' => $segment['byte_end'] ?? null,
                'token_count' => $segment['token_count'] ?? null,
                'embedding_model_id' => null,
                'embedding_dim' => 0,
                'metadata' => $this->encodeMetadata($segment['metadata'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        KnowledgeNode::query()->insert($rows);
        $this->indexNodes($ids);

        return $ids;
    }

    public function writeQaCanonicalNodes(
        KnowledgeBase $knowledgeBase,
        KnowledgeQaEntry $entry,
        array $items,
    ): array {
        if ($items === []) {
            return [];
        }

        $now = now();
        $rows = [];
        $ids = [];

        foreach ($items as $item) {
            $id = (string) Str::ulid();
            $ids[] = $id;
            $rows[] = [
                'id' => $id,
                'knowledge_base_id' => (string) $knowledgeBase->id,
                'document_id' => null,
                'qa_entry_id' => (string) $entry->id,
                'qa_question_id' => filled($item['qa_question_id'] ?? null)
                    ? (string) $item['qa_question_id']
                    : null,
                'parent_id' => null,
                'strategy' => KnowledgeIndexingStrategy::Text,
                'level' => 0,
                'kind' => KnowledgeNodeKind::Segment,
                'content' => (string) $item['content'],
                'content_format' => (string) ($item['content_format'] ?? 'text'),
                'heading_path' => null,
                'byte_start' => null,
                'byte_end' => null,
                'token_count' => null,
                'embedding_model_id' => null,
                'embedding_dim' => 0,
                'metadata' => $this->encodeMetadata($item['metadata'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        KnowledgeNode::query()->insert($rows);
        $this->indexNodes($ids);

        return $ids;
    }

    public function attachVectors(
        int $embeddingDimension,
        array $embeddings,
    ): void {
        if ($embeddings === [] || $embeddingDimension <= 0) {
            return;
        }

        $nodeIds = array_map('strval', array_keys($embeddings));
        $modelId = $this->embeddingModelId();
        $nodes = KnowledgeNode::query()->whereIn('id', $nodeIds)->get(['id', 'embedding_dim']);

        DB::connection('sqlite_rag')->transaction(function () use (
            $nodes,
            $embeddings,
            $embeddingDimension,
            $nodeIds,
            $modelId,
        ): void {
            $oldIdsByDimension = [];
            foreach ($nodes as $node) {
                $oldDimension = (int) $node->embedding_dim;
                if ($oldDimension > 0 && $oldDimension !== $embeddingDimension) {
                    $oldIdsByDimension[$oldDimension][] = (string) $node->id;
                }
            }

            foreach ($oldIdsByDimension as $dimension => $ids) {
                $this->vectorTables->deleteVectors($dimension, $ids);
            }

            foreach ($embeddings as $nodeId => $embedding) {
                $this->vectorTables->upsertVector(
                    $embeddingDimension,
                    (string) $nodeId,
                    array_map(static fn ($value): float => (float) $value, $embedding),
                );
            }

            KnowledgeNode::query()
                ->whereIn('id', $nodeIds)
                ->update([
                    'embedding_dim' => $embeddingDimension,
                    'embedding_model_id' => $modelId,
                    'updated_at' => now(),
                ]);
        });
    }

    public function writeSummaryNode(
        KnowledgeBase $knowledgeBase,
        KnowledgeDocument $document,
        int $level,
        ?string $parentId,
        string $content,
        int $embeddingDimension,
        ?array $embedding,
        array $childrenIds,
        ?array $metadata = null,
    ): string {
        $id = (string) Str::ulid();
        $now = now();
        $vector = $embedding === null ? null : array_map(
            static fn ($value): float => (float) $value,
            $embedding,
        );
        $hasVector = $vector !== null && $vector !== [];

        $metadataPayload = ['children_ids' => $childrenIds];
        if ($metadata !== null) {
            $metadataPayload = array_merge($metadataPayload, $metadata);
        }

        DB::connection('sqlite_rag')->transaction(function () use (
            $id,
            $knowledgeBase,
            $document,
            $level,
            $parentId,
            $content,
            $embeddingDimension,
            $hasVector,
            $metadataPayload,
            $now,
            $vector,
        ): void {
            KnowledgeNode::query()->insert([
                'id' => $id,
                'knowledge_base_id' => (string) $knowledgeBase->id,
                'document_id' => (string) $document->id,
                'qa_entry_id' => null,
                'qa_question_id' => null,
                'parent_id' => $parentId,
                'strategy' => KnowledgeIndexingStrategy::Raptor,
                'level' => $level,
                'kind' => KnowledgeNodeKind::Summary,
                'content' => $content,
                'content_format' => 'markdown',
                'heading_path' => null,
                'byte_start' => null,
                'byte_end' => null,
                'token_count' => null,
                'embedding_model_id' => $hasVector ? $this->embeddingModelId() : null,
                'embedding_dim' => $hasVector ? $embeddingDimension : 0,
                'metadata' => json_encode($metadataPayload, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($hasVector) {
                $this->vectorTables->upsertVector($embeddingDimension, $id, $vector);
            }
        });

        $this->indexNodes([$id]);

        return $id;
    }

    public function setParentForNodes(array $childrenIds, string $parentId): void
    {
        if ($childrenIds === []) {
            return;
        }

        KnowledgeNode::query()
            ->whereIn('id', $childrenIds)
            ->where('strategy', KnowledgeIndexingStrategy::Raptor)
            ->update(['parent_id' => $parentId, 'updated_at' => now()]);
    }

    private function indexNodes(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        KnowledgeNode::query()->whereIn('id', $ids)->get()->searchableSync();
    }

    private function embeddingModelId(): ?string
    {
        $model = $this->engine->current()->pinnedEmbeddingModel();

        return $model !== null ? (string) $model->id : null;
    }

    private function encodeMetadata(?array $metadata): ?string
    {
        return $metadata === null || $metadata === []
            ? null
            : json_encode($metadata, JSON_THROW_ON_ERROR);
    }
}
