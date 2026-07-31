<?php

namespace App\Actions\KnowledgeBase;

use App\Actions\Attachment\DeleteAttachmentAction;
use App\Actions\KnowledgeBase\Document\DeleteKnowledgeDocumentAction;
use App\Exceptions\BusinessException;
use App\Models\ExperienceExtraction;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeGroup;
use App\Models\KnowledgeQaAnswer;
use App\Models\KnowledgeQaEntry;
use App\Models\KnowledgeQaQuestion;
use App\Models\ReceptionPlanKnowledgeBase;
use App\Services\KnowledgeBase\KnowledgeBaseReferenceLock;
use App\Services\KnowledgeBase\KnowledgeNodeRepository;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * 删除知识库及其文档、问答、分组、附件和检索数据。
 */
class DeleteKnowledgeBaseAction
{
    use AsAction;

    /**
     * 注入引用锁及节点、文档和附件清理流程。
     */
    public function __construct(
        private readonly KnowledgeNodeRepository $nodes,
        private readonly DeleteKnowledgeDocumentAction $deleteDocument,
        private readonly DeleteAttachmentAction $deleteAttachment,
        private readonly KnowledgeBaseReferenceLock $referenceLock,
    ) {}

    /**
     * 删除未被业务使用的知识库及其关联资源。
     */
    public function handle(KnowledgeBase $knowledgeBase): void
    {
        $knowledgeBaseId = (string) $knowledgeBase->id;

        try {
            $this->referenceLock->run([$knowledgeBaseId], function (Closure $refreshLock) use ($knowledgeBaseId): void {
                $current = KnowledgeBase::query()
                    ->useWritePdo()
                    ->findOrFail($knowledgeBaseId);

                $isInUse = ReceptionPlanKnowledgeBase::query()
                    ->useWritePdo()
                    ->where('knowledge_base_id', $current->id)
                    ->exists()
                    || ExperienceExtraction::query()
                        ->useWritePdo()
                        ->where('knowledge_base_id', $current->id)
                        ->exists();

                if ($isInUse) {
                    throw new BusinessException(__('knowledge_base.messages.in_use'));
                }

                try {
                    $current->loadMissing('avatar');
                    $avatar = $current->avatar;

                    $current->documents()
                        ->eachById(function (KnowledgeDocument $document) use ($refreshLock): void {
                            $refreshLock();
                            $this->deleteDocument->handle($document);
                        });

                    $refreshLock();
                    $this->nodes->purgeAllForKnowledgeBase($current);
                    $refreshLock();

                    if ($avatar !== null) {
                        $this->deleteAttachment->handle($avatar);
                    }

                    $refreshLock();
                    DB::transaction(function () use ($current): void {
                        $qaEntryIds = KnowledgeQaEntry::query()
                            ->where('knowledge_base_id', $current->id)
                            ->pluck('id');

                        if ($qaEntryIds->isNotEmpty()) {
                            KnowledgeQaQuestion::query()
                                ->whereIn('knowledge_qa_entry_id', $qaEntryIds)
                                ->delete();
                            KnowledgeQaAnswer::query()
                                ->whereIn('knowledge_qa_entry_id', $qaEntryIds)
                                ->delete();
                            KnowledgeQaEntry::query()
                                ->whereIn('id', $qaEntryIds)
                                ->delete();
                        }

                        KnowledgeGroup::query()
                            ->where('knowledge_base_id', $current->id)
                            ->delete();

                        if (! $current->delete()) {
                            throw new RuntimeException(sprintf(
                                'Failed to delete knowledge base [%s].',
                                $current->id,
                            ));
                        }
                    });
                } catch (Throwable $exception) {
                    Log::warning('[knowledge] 知识库删除失败', [
                        'knowledge_base_id' => $knowledgeBaseId,
                        'message' => $exception->getMessage(),
                    ]);

                    throw $exception;
                }
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('[knowledge] 知识库删除等待引用操作超时', [
                'knowledge_base_id' => $knowledgeBaseId,
            ]);

            throw new BusinessException(
                __('knowledge_base.messages.operation_busy'),
                previous: $exception,
            );
        }
    }

    /**
     * 接收删除知识库请求并返回列表页。
     */
    public function asController(Request $request, string $knowledgeBase): RedirectResponse
    {
        $knowledgeBaseModel = KnowledgeBase::query()
            ->findOrFail($knowledgeBase);

        $this->handle($knowledgeBaseModel);

        return redirect()->route('app.manage.knowledge-bases.index');
    }
}
