<?php

namespace App\Actions\KnowledgeBase\Qa;

use App\Actions\KnowledgeBase\Indexing\DispatchKnowledgeQaEntryPipelineAction;
use App\Data\KnowledgeBase\FormCreateKnowledgeQaEntryData;
use App\Enums\KnowledgeQaEntryStatus;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeGroup;
use App\Models\KnowledgeQaEntry;
use App\Services\KnowledgeBase\KnowledgeQaEntryWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 在问答知识库下创建问答条目，并保存相似问法与多答案。
 */
class CreateKnowledgeQaEntryAction
{
    use AsAction;

    public function __construct(
        private readonly DispatchKnowledgeQaEntryPipelineAction $pipeline,
        private readonly KnowledgeQaEntryWriter $writer,
    ) {}

    /**
     * 创建问答条目聚合。
     */
    public function handle(KnowledgeBase $knowledgeBase, FormCreateKnowledgeQaEntryData $data, ?string $creatorUserId = null): KnowledgeQaEntry
    {
        $this->writer->assertQaKnowledgeBase($knowledgeBase);

        $answers = $data->normalizedAnswers();
        if ($answers === []) {
            throw ValidationException::withMessages([
                'answers' => __('knowledge_base.qa.errors.answer_required'),
            ]);
        }

        $group = $this->resolveTargetGroup($knowledgeBase, filled($data->group_id) ? $data->group_id : null);
        $question = trim($data->question);
        $similarQuestions = $data->normalizedSimilarQuestions();

        $entry = DB::transaction(function () use ($knowledgeBase, $group, $creatorUserId, $question, $similarQuestions, $answers): KnowledgeQaEntry {
            /** @var KnowledgeQaEntry $entry */
            $entry = KnowledgeQaEntry::query()->create([
                'knowledge_base_id' => $knowledgeBase->id,
                'group_id' => $group->id,
                'created_by_user_id' => $creatorUserId,
                'question' => $question,
                'status' => KnowledgeQaEntryStatus::Pending,
                'error_message' => null,
                'sort_order' => 0,
            ]);

            $this->writer->syncSimilarQuestions($entry, $similarQuestions);
            $this->writer->syncAnswers($entry, $answers);

            return $entry->fresh(['similarQuestions', 'answers']) ?? $entry;
        });

        $this->pipeline->handle($entry);

        return $entry->fresh(['similarQuestions', 'answers']) ?? $entry;
    }

    /**
     * 接收添加问答提交并跳回当前知识库 / 分组视图。
     */
    public function asController(Request $request, string $knowledgeBase): RedirectResponse
    {

        $kb = KnowledgeBase::query()

            ->findOrFail($knowledgeBase);

        $entry = $this->handle(
            $kb,
            FormCreateKnowledgeQaEntryData::from($request),
            (string) $request->user()?->id,
        );

        return redirect()->route('app.manage.knowledge-bases.index', [
            'kb' => $kb->id,
            'group' => (string) $entry->group_id,
        ]);
    }

    /**
     * 校验目标分组属于当前知识库；未传分组时回退到默认分组。
     */
    protected function resolveTargetGroup(KnowledgeBase $knowledgeBase, ?string $groupId): KnowledgeGroup
    {
        if ($groupId === null) {
            $defaultGroup = $knowledgeBase->defaultDocumentGroup()->first();

            if ($defaultGroup) {
                return $defaultGroup;
            }

            throw ValidationException::withMessages([
                'group_id' => __('knowledge_base.documents.errors.default_group_missing'),
            ]);
        }

        $group = KnowledgeGroup::query()
            ->where('id', $groupId)
            ->where('knowledge_base_id', $knowledgeBase->id)
            ->first();

        if (! $group) {
            throw ValidationException::withMessages([
                'group_id' => __('knowledge_base.documents.errors.invalid_group'),
            ]);
        }

        return $group;
    }
}
