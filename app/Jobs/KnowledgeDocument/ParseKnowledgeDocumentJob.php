<?php

namespace App\Jobs\KnowledgeDocument;

use App\Actions\KnowledgeBase\Indexing\DispatchKnowledgeDocumentPipelineAction;
use App\Actions\KnowledgeBase\Indexing\ParseKnowledgeDocumentAction;
use App\Models\KnowledgeDocument;
use App\Services\KnowledgeBase\KnowledgeDocumentPipelineLock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * 文档解析队列任务，解析成功后派发启用的索引阶段。
 */
class ParseKnowledgeDocumentJob implements ShouldQueue
{
    use Queueable;

    /**
     * 大文档解析慢；给到 5 分钟队列超时与 3 次重试。
     */
    public int $timeout = 300;

    public int $tries = 3;

    /**
     * 创建文档解析任务。
     */
    public function __construct(public readonly string $documentId)
    {
        $this->queue = 'knowledge';
    }

    /**
     * 执行文档解析并派发后续索引任务。
     */
    public function handle(
        ParseKnowledgeDocumentAction $parseAction,
        DispatchKnowledgeDocumentPipelineAction $dispatcher,
        KnowledgeDocumentPipelineLock $pipelineLock,
    ): void {
        $parsed = $pipelineLock->runParsing($this->documentId, 30, function () use ($parseAction): bool {
            $document = KnowledgeDocument::query()->find($this->documentId);
            if ($document === null) {
                Log::info('[knowledge] 文档不存在，跳过解析任务', [
                    'document_id' => $this->documentId,
                ]);

                return false;
            }

            $parseAction->handle($document);

            return true;
        });

        if (! $parsed) {
            return;
        }

        $document = KnowledgeDocument::query()->find($this->documentId);
        if ($document !== null) {
            $dispatcher->dispatchIndexingForParsedDocument($document);
        }
    }
}
