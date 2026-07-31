<?php

namespace App\Actions\KnowledgeBase\Indexing;

use App\Enums\KnowledgeDocumentIndexingStatus;
use App\Enums\KnowledgeIndexingStrategy;
use App\Enums\KnowledgeQaEntryStatus;
use App\Jobs\KnowledgeQa\IndexVectorKnowledgeQaEntryJob;
use App\Models\KnowledgeQaEntry;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 问答条目索引流水线，先写 canonical 节点 + 全文索引，再按应用配置投递问题侧向量索引。
 */
class DispatchKnowledgeQaEntryPipelineAction
{
    use AsAction;

    public function __construct(
        private readonly WriteCanonicalChunksAction $canonicalWriter,
    ) {}

    /**
     * 为问答条目重建 canonical / 全文索引，并同步向量阶段状态。
     */
    public function handle(KnowledgeQaEntry $entry): void
    {
        $entry->refresh();
        $entry->loadMissing(['knowledgeBase', 'similarQuestions', 'answers']);
        $knowledgeBase = $entry->knowledgeBase;
        if ($knowledgeBase === null) {
            return;
        }

        $this->canonicalWriter->forQaEntry($entry);

        if ($knowledgeBase->hasIndexingStrategy(KnowledgeIndexingStrategy::Vector)) {
            $entry->forceFill([
                'vector_status' => KnowledgeDocumentIndexingStatus::Pending,
                'vector_error' => null,
                'vector_indexed_at' => null,
                'status' => KnowledgeQaEntryStatus::Pending,
                'error_message' => null,
            ])->save();

            IndexVectorKnowledgeQaEntryJob::dispatch((string) $entry->id);

            return;
        }

        $entry->forceFill([
            'vector_status' => KnowledgeDocumentIndexingStatus::Idle,
            'vector_error' => null,
            'vector_indexed_at' => null,
            'status' => KnowledgeQaEntryStatus::Indexed,
            'error_message' => null,
        ])->save();
    }
}
