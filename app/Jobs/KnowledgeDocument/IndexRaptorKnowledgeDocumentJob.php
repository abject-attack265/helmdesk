<?php

namespace App\Jobs\KnowledgeDocument;

use App\Actions\KnowledgeBase\Indexing\IndexKnowledgeDocumentRaptorAction;
use App\Models\KnowledgeDocument;
use App\Services\KnowledgeBase\KnowledgeDocumentPipelineLock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * RAPTOR 摘要索引队列任务：聚类 + 摘要 + 写 raptor 节点。
 *
 * 单 Job 即可完成全部层级（在 Action 内循环），失败重试整段。
 */
class IndexRaptorKnowledgeDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 2;

    /**
     * 创建 RAPTOR 索引任务。
     */
    public function __construct(public readonly string $documentId)
    {
        $this->queue = 'knowledge';
    }

    /**
     * 执行文档 RAPTOR 摘要索引。
     */
    public function handle(
        IndexKnowledgeDocumentRaptorAction $action,
        KnowledgeDocumentPipelineLock $pipelineLock,
    ): void {
        $pipelineLock->runRaptorIndexing($this->documentId, 30, function () use ($action): void {
            $document = KnowledgeDocument::query()->find($this->documentId);
            if ($document === null) {
                Log::info('[knowledge] 文档不存在，跳过 RAPTOR 索引任务', [
                    'document_id' => $this->documentId,
                ]);

                return;
            }

            $action->handle($document);
        });
    }
}
