<?php

namespace App\Actions\KnowledgeBase\Document;

use App\Data\KnowledgeBase\FormMoveKnowledgeDocumentData;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 将知识库文档移动到同一知识库下的另一个分组。
 */
class MoveKnowledgeDocumentAction
{
    use AsAction;

    /**
     * 更新文档归属分组。
     */
    public function handle(KnowledgeDocument $document, KnowledgeGroup $group): void
    {
        if ((string) $document->knowledge_base_id !== (string) $group->knowledge_base_id) {
            throw ValidationException::withMessages([
                'group_id' => __('knowledge_base.documents.errors.invalid_group'),
            ]);
        }

        $document->update(['group_id' => $group->id]);
    }

    /**
     * 处理文档移动分组请求。
     */
    public function asController(Request $request, string $knowledgeBase, string $document): RedirectResponse
    {

        $kb = KnowledgeBase::query()

            ->findOrFail($knowledgeBase);

        $documentModel = KnowledgeDocument::query()
            ->where('knowledge_base_id', $kb->id)
            ->findOrFail($document);

        $data = FormMoveKnowledgeDocumentData::from($request);

        $group = KnowledgeGroup::query()
            ->where('knowledge_base_id', $kb->id)
            ->find($data->group_id);

        if (! $group) {
            throw ValidationException::withMessages([
                'group_id' => __('knowledge_base.documents.errors.invalid_group'),
            ]);
        }

        $this->handle($documentModel, $group);

        return back();
    }
}
