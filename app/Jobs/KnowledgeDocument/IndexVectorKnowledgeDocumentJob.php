<?php

namespace App\Jobs\KnowledgeDocument;

use App\Actions\KnowledgeBase\Indexing\IndexKnowledgeDocumentVectorAction;
use App\Models\KnowledgeDocument;
use App\Services\KnowledgeBase\KnowledgeDocumentPipelineLock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * 向量索引队列任务：批量分块 + 嵌入 + 把向量挂到节点。
 */
class IndexVectorKnowledgeDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 3;

    /**
     * 创建向量索引任务。
     */
    public function __construct(public readonly string $documentId)
    {
        $this->queue = 'knowledge';
    }

    /**
     * 执行文档向量索引。
     */
    public function handle(
        IndexKnowledgeDocumentVectorAction $action,
        KnowledgeDocumentPipelineLock $pipelineLock,
    ): void {
        $pipelineLock->runVectorIndexing($this->documentId, 30, function () use ($action): void {
            $document = KnowledgeDocument::query()->find($this->documentId);
            if ($document === null) {
                Log::info('[knowledge] 文档不存在，跳过向量索引任务', [
                    'document_id' => $this->documentId,
                ]);

                return;
            }

            $action->handle($document);
        });
    }
}
