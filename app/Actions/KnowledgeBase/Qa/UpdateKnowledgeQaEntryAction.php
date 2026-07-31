<?php

namespace App\Actions\KnowledgeBase\Qa;

use App\Actions\KnowledgeBase\Indexing\DispatchKnowledgeQaEntryPipelineAction;
use App\Data\KnowledgeBase\FormCreateKnowledgeQaEntryData;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeQaEntry;
use App\Services\KnowledgeBase\KnowledgeQaEntryWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 编辑问答条目的主问题、相似问法和多答案。
 */
class UpdateKnowledgeQaEntryAction
{
    use AsAction;

    public function __construct(
        private readonly DispatchKnowledgeQaEntryPipelineAction $pipeline,
        private readonly KnowledgeQaEntryWriter $writer,
    ) {}

    /**
     * 更新问答条目聚合。
     */
    public function handle(KnowledgeQaEntry $entry, FormCreateKnowledgeQaEntryData $data): void
    {
        $answers = $data->normalizedAnswers();
        if ($answers === []) {
            throw ValidationException::withMessages([
                'answers' => __('knowledge_base.qa.errors.answer_required'),
            ]);
        }

        $entry->loadMissing('knowledgeBase');
        $this->writer->assertQaKnowledgeBase($entry->knowledgeBase);
        $question = trim($data->question);
        $similarQuestions = $data->normalizedSimilarQuestions();

        DB::transaction(function () use ($entry, $question, $similarQuestions, $answers): void {
            $entry->update([
                'question' => $question,
                'error_message' => null,
            ]);

            $this->writer->syncSimilarQuestions($entry, $similarQuestions);
            $this->writer->syncAnswers($entry, $answers);
        });

        $this->pipeline->handle($entry);
    }

    /**
     * 接收编辑问答提交并跳回当前知识库 / 分组视图。
     */
    public function asController(Request $request, string $knowledgeBase, string $entry): RedirectResponse
    {

        $kb = KnowledgeBase::query()

            ->findOrFail($knowledgeBase);

        $entryModel = KnowledgeQaEntry::query()
            ->with('knowledgeBase')
            ->where('knowledge_base_id', $kb->id)
            ->findOrFail($entry);

        $this->handle($entryModel, FormCreateKnowledgeQaEntryData::from($request));

        return redirect()->route('app.manage.knowledge-bases.index', [
            'kb' => $kb->id,
            'group' => (string) $entryModel->group_id,
        ]);
    }
}
