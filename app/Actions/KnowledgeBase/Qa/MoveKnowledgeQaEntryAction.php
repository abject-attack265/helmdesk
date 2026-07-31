<?php

namespace App\Actions\KnowledgeBase\Qa;

use App\Actions\KnowledgeBase\Indexing\DispatchKnowledgeQaEntryPipelineAction;
use App\Data\KnowledgeBase\FormMoveKnowledgeDocumentData;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeGroup;
use App\Models\KnowledgeQaEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 将问答条目移动到同一知识库下的另一个分组。
 */
class MoveKnowledgeQaEntryAction
{
    use AsAction;

    /**
     * 注入问答索引流水线。
     */
    public function __construct(
        private readonly DispatchKnowledgeQaEntryPipelineAction $pipeline,
    ) {}

    /**
     * 更新问答条目归属分组。
     */
    public function handle(KnowledgeQaEntry $entry, KnowledgeGroup $group): void
    {
        if ((string) $entry->knowledge_base_id !== (string) $group->knowledge_base_id) {
            throw ValidationException::withMessages([
                'group_id' => __('knowledge_base.documents.errors.invalid_group'),
            ]);
        }

        $entry->update(['group_id' => $group->id]);
        $this->pipeline->handle($entry);
    }

    /**
     * 处理问答移动分组请求。
     */
    public function asController(Request $request, string $knowledgeBase, string $entry): RedirectResponse
    {

        $kb = KnowledgeBase::query()

            ->findOrFail($knowledgeBase);

        $entryModel = KnowledgeQaEntry::query()
            ->where('knowledge_base_id', $kb->id)
            ->findOrFail($entry);

        $data = FormMoveKnowledgeDocumentData::from($request);

        $group = KnowledgeGroup::query()
            ->where('knowledge_base_id', $kb->id)
            ->find($data->group_id);

        if (! $group) {
            throw ValidationException::withMessages([
                'group_id' => __('knowledge_base.documents.errors.invalid_group'),
            ]);
        }

        $this->handle($entryModel, $group);

        return back();
    }
}
