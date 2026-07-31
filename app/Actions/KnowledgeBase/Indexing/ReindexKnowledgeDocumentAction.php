<?php

namespace App\Actions\KnowledgeBase\Indexing;

use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 用户在文档列表点击“重新处理”时重新执行文档处理流程。
 */
class ReindexKnowledgeDocumentAction
{
    use AsAction;

    /**
     * 注入文档处理流程。
     */
    public function __construct(
        private readonly DispatchKnowledgeDocumentPipelineAction $dispatcher,
    ) {}

    /**
     * 重新投递文档读取和处理任务。
     */
    public function handle(KnowledgeDocument $document): void
    {
        $this->dispatcher->handle($document, forceReparse: true);
    }

    /**
     * 重新处理文档并返回当前列表。
     */
    public function asController(Request $request, string $knowledgeBase, string $document): RedirectResponse
    {

        $kb = KnowledgeBase::query()
            ->findOrFail($knowledgeBase);

        $documentModel = KnowledgeDocument::query()
            ->where('knowledge_base_id', $kb->id)
            ->findOrFail($document);

        $this->handle($documentModel);

        return back();
    }
}
