<?php

namespace App\Actions\KnowledgeBase\Document;

use App\Actions\Attachment\DeleteAttachmentAction;
use App\Exceptions\BusinessException;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Services\KnowledgeBase\KnowledgeDocumentPipelineLock;
use App\Services\KnowledgeBase\KnowledgeNodeRepository;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 删除知识库文档及其原文件、知识节点和检索索引。
 */
class DeleteKnowledgeDocumentAction
{
    use AsAction;

    /**
     * 注入附件删除、知识节点清理和流水线锁服务。
     */
    public function __construct(
        private readonly DeleteAttachmentAction $deleteAttachment,
        private readonly KnowledgeNodeRepository $nodes,
        private readonly KnowledgeDocumentPipelineLock $pipelineLock,
    ) {}

    /**
     * 删除指定文档及其在 RAG 库里的全部派生数据。
     */
    public function handle(KnowledgeDocument $document): void
    {
        try {
            $this->pipelineLock->runExclusively((string) $document->id, 5, function () use ($document): void {
                $current = KnowledgeDocument::query()->find($document->id);
                if ($current === null) {
                    Log::info('[knowledge] 文档不存在，跳过删除', [
                        'document_id' => (string) $document->id,
                    ]);

                    return;
                }

                $current->loadMissing('originalFile');
                $knowledgeBaseId = (string) $current->knowledge_base_id;
                $attachmentId = $current->originalFile?->id;
                $this->nodes->purgeAllForDocument($current);

                if ($current->originalFile) {
                    $this->deleteAttachment->handle($current->originalFile);
                }

                $current->delete();

                Log::info('[knowledge] 知识文档已删除', [
                    'knowledge_base_id' => $knowledgeBaseId,
                    'document_id' => (string) $current->id,
                    'attachment_id' => $attachmentId,
                ]);
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('[knowledge] 知识文档删除等待流水线超时', [
                'knowledge_base_id' => (string) $document->knowledge_base_id,
                'document_id' => (string) $document->id,
            ]);

            throw new BusinessException(
                __('knowledge_base.documents.errors.pipeline_busy'),
                previous: $exception,
            );
        }
    }

    /**
     * 处理「删除文档」按钮的提交，并跳回当前知识库 / 分组视图。
     */
    public function asController(Request $request, string $knowledgeBase, string $document): RedirectResponse
    {

        $kb = KnowledgeBase::query()

            ->findOrFail($knowledgeBase);

        $documentModel = KnowledgeDocument::query()
            ->where('knowledge_base_id', $kb->id)
            ->findOrFail($document);

        $groupId = filled($documentModel->group_id) ? (string) $documentModel->group_id : null;

        $this->handle($documentModel);

        $query = ['kb' => $kb->id];
        if ($groupId !== null) {
            $query['group'] = $groupId;
        }

        return redirect()->route('app.manage.knowledge-bases.index', [
            ...$query,
        ]);
    }
}
