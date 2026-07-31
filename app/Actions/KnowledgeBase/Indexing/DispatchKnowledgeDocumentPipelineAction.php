<?php

namespace App\Actions\KnowledgeBase\Indexing;

use App\Enums\KnowledgeDocumentIndexingStatus;
use App\Enums\KnowledgeDocumentParseStatus;
use App\Enums\KnowledgeIndexingStrategy;
use App\Jobs\KnowledgeDocument\IndexRaptorKnowledgeDocumentJob;
use App\Jobs\KnowledgeDocument\IndexVectorKnowledgeDocumentJob;
use App\Jobs\KnowledgeDocument\ParseKnowledgeDocumentJob;
use App\Models\KnowledgeDocument;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 文档索引流水线编排器，负责更新阶段状态并投递解析、向量索引、RAPTOR 索引任务。
 */
class DispatchKnowledgeDocumentPipelineAction
{
    use AsAction;

    /**
     * 触发整条流水线，标记待处理状态并投递解析任务。
     */
    public function handle(KnowledgeDocument $document, bool $forceReparse = true): void
    {
        $document->refresh();
        $kb = $document->knowledgeBase;
        if ($kb === null) {
            throw new LogicException(sprintf(
                'Knowledge document [%s] has no knowledge base.',
                $document->id,
            ));
        }

        $strategies = $kb->enabledIndexingStrategies();

        $updates = [
            'parse_status' => $forceReparse ? KnowledgeDocumentParseStatus::Pending : $document->parse_status,
            'parse_error' => $forceReparse ? null : $document->parse_error,
            'vector_status' => $this->statusForStrategy($strategies, KnowledgeIndexingStrategy::Vector),
            'vector_error' => null,
            'raptor_status' => $this->statusForStrategy($strategies, KnowledgeIndexingStrategy::Raptor),
            'raptor_error' => null,
        ];

        $document->forceFill($updates)->save();
        $document->forceFill(['status' => $document->deriveOverallStatus($kb)])->save();

        if ($forceReparse || $document->parse_status !== KnowledgeDocumentParseStatus::Succeeded) {
            ParseKnowledgeDocumentJob::dispatch((string) $document->id);

            return;
        }

        $this->dispatchIndexingForParsedDocument($document);
    }

    /**
     * 为已完成解析的文档派发启用的索引任务。
     */
    public function dispatchIndexingForParsedDocument(KnowledgeDocument $document): void
    {
        $kb = $document->knowledgeBase;
        if ($kb === null) {
            throw new LogicException(sprintf(
                'Knowledge document [%s] has no knowledge base.',
                $document->id,
            ));
        }

        $documentId = (string) $document->id;
        if ($kb->hasIndexingStrategy(KnowledgeIndexingStrategy::Vector)) {
            IndexVectorKnowledgeDocumentJob::dispatch($documentId);
        }
        if ($kb->hasIndexingStrategy(KnowledgeIndexingStrategy::Raptor)) {
            IndexRaptorKnowledgeDocumentJob::dispatch($documentId);
        }
    }

    /**
     * 根据知识库策略配置返回文档索引阶段的初始状态。
     *
     * @param  list<KnowledgeIndexingStrategy>  $strategies
     */
    private function statusForStrategy(array $strategies, KnowledgeIndexingStrategy $strategy): KnowledgeDocumentIndexingStatus
    {
        return in_array($strategy, $strategies, true)
            ? KnowledgeDocumentIndexingStatus::Pending
            : KnowledgeDocumentIndexingStatus::Idle;
    }
}
