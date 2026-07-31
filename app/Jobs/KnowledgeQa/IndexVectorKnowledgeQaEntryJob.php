<?php

namespace App\Jobs\KnowledgeQa;

use App\Actions\KnowledgeBase\Indexing\IndexKnowledgeQaEntryVectorAction;
use App\Models\KnowledgeQaEntry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * 问答向量索引队列任务：嵌入主问题和相似问法并写入向量表。
 */
class IndexVectorKnowledgeQaEntryJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 3;

    /**
     * 创建问答向量索引任务。
     */
    public function __construct(public readonly string $entryId)
    {
        $this->queue = 'knowledge';
    }

    /**
     * 执行问答向量索引。
     */
    public function handle(IndexKnowledgeQaEntryVectorAction $action): void
    {
        $entry = KnowledgeQaEntry::query()->find($this->entryId);
        if ($entry === null) {
            Log::info('IndexVectorKnowledgeQaEntryJob: entry missing, skipped.', [
                'qa_entry_id' => $this->entryId,
            ]);

            return;
        }

        $action->handle($entry);
    }
}
