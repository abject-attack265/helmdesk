<?php

namespace App\Actions\KnowledgeBase\Indexing;

use App\Enums\KnowledgeDocumentIndexingStatus;
use App\Enums\KnowledgeIndexingStrategy;
use App\Enums\KnowledgeNodeKind;
use App\Enums\KnowledgeQaEntryStatus;
use App\Exceptions\BusinessException;
use App\Models\KnowledgeNode;
use App\Models\KnowledgeQaEntry;
use App\Services\KnowledgeBase\KnowledgeEmbeddingService;
use App\Services\KnowledgeBase\KnowledgeEngine;
use App\Services\KnowledgeBase\KnowledgeNodeRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * 问答条目向量索引器。
 *
 *  - 仅给问题角色（主问题 / 相似问法）的 canonical 节点附加向量；
 *  - 答案文本通过 FTS 已经可被检索，不需要再走嵌入。
 */
class IndexKnowledgeQaEntryVectorAction
{
    use AsAction;

    public function __construct(
        private readonly KnowledgeEmbeddingService $embedder,
        private readonly KnowledgeNodeRepository $nodes,
        private readonly KnowledgeEngine $engine,
    ) {}

    /**
     * 为指定问答条目构建问题侧向量索引。
     */
    public function handle(KnowledgeQaEntry $entry): void
    {
        $entry->refresh();
        $entry->loadMissing(['knowledgeBase', 'similarQuestions', 'answers']);
        $knowledgeBase = $entry->knowledgeBase;
        if ($knowledgeBase === null) {
            return;
        }
        $engine = $this->engine->current();

        if (! $knowledgeBase->hasIndexingStrategy(KnowledgeIndexingStrategy::Vector)) {
            $this->markIdle($entry);

            return;
        }

        $entry->forceFill([
            'vector_status' => KnowledgeDocumentIndexingStatus::Processing,
            'vector_error' => null,
            'status' => KnowledgeQaEntryStatus::Indexing,
            'error_message' => null,
        ])->save();

        try {
            // 嵌入模型取该应用生效的向量模型（同规格备份打散后首个），取不到直接报错。
            $embeddingModel = $engine->pinnedEmbeddingModel();
            if ($embeddingModel === null || $embeddingModel->provider === null) {
                throw new BusinessException(__('knowledge_base.messages.invalid_embedding_model'));
            }

            $questionNodes = $this->questionNodesFor($entry);
            if ($questionNodes->isEmpty()) {
                throw new BusinessException(__('knowledge_base.qa.errors.question_required'));
            }

            $contents = $questionNodes->map(static fn (KnowledgeNode $node): string => (string) $node->content)->all();
            [$dimension, $vectors] = $this->embedder->embedTexts($embeddingModel, $contents, $engine->embeddingDimension());
            if ($dimension <= 0 || count($vectors) !== $questionNodes->count()) {
                throw new BusinessException(__('knowledge_base.documents.errors.embedding_failed'));
            }

            $embeddings = [];
            foreach ($questionNodes as $idx => $node) {
                $embeddings[(string) $node->id] = $vectors[$idx];
            }
            $this->nodes->attachVectors($dimension, $embeddings);

            $entry->forceFill([
                'vector_status' => KnowledgeDocumentIndexingStatus::Succeeded,
                'vector_error' => null,
                'vector_indexed_at' => now(),
                'status' => KnowledgeQaEntryStatus::Indexed,
                'error_message' => null,
            ])->save();

            Log::info('[knowledge] 问答条目向量索引完成', [
                'knowledge_base_id' => (string) $knowledgeBase->id,
                'entry_id' => (string) $entry->id,
                'node_count' => $questionNodes->count(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Q&A vector indexing failed.', [
                'qa_entry_id' => $entry->id,
                'message' => $exception->getMessage(),
            ]);
            $entry->forceFill([
                'vector_status' => KnowledgeDocumentIndexingStatus::Failed,
                'vector_error' => $exception->getMessage(),
                'status' => KnowledgeQaEntryStatus::Failed,
                'error_message' => $exception->getMessage(),
            ])->save();
            throw $exception;
        }
    }

    /**
     * @return Collection<int, KnowledgeNode>
     */
    private function questionNodesFor(KnowledgeQaEntry $entry): Collection
    {
        return KnowledgeNode::query()
            ->where('qa_entry_id', $entry->id)
            ->where('strategy', KnowledgeIndexingStrategy::Text)
            ->where('kind', KnowledgeNodeKind::Segment)
            ->get()
            ->filter(static function (KnowledgeNode $node): bool {
                $role = $node->metadata['qa_role'] ?? null;

                return $role === 'qa_primary' || $role === 'qa_similar';
            })
            ->values();
    }

    /**
     * 应用未启用向量索引时，问答条目仅维持全文索引并标记为已就绪。
     */
    private function markIdle(KnowledgeQaEntry $entry): void
    {
        $entry->forceFill([
            'vector_status' => KnowledgeDocumentIndexingStatus::Idle,
            'vector_error' => null,
            'vector_indexed_at' => null,
            'status' => KnowledgeQaEntryStatus::Indexed,
            'error_message' => null,
        ])->save();
    }
}
