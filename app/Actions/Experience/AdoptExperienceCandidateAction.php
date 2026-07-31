<?php

namespace App\Actions\Experience;

use App\Actions\KnowledgeBase\Qa\CreateKnowledgeQaEntryAction;
use App\Data\Experience\FormAdoptExperienceCandidateData;
use App\Data\KnowledgeBase\FormCreateKnowledgeQaEntryData;
use App\Enums\ExperienceCandidateStatus;
use App\Exceptions\BusinessException;
use App\Models\ExperienceCandidate;
use App\Models\KnowledgeQaEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 采纳一条候选经验：用管理员润色后的内容在任务绑定的问答知识库创建 QA 问答对，并把候选标记为已采纳。
 *
 * 落库复用 CreateKnowledgeQaEntryAction，canonical/向量索引管道自动串联。
 */
class AdoptExperienceCandidateAction
{
    use AsAction;

    /**
     * 在任务绑定的问答库创建 QA 条目并回写候选状态，返回生成的条目。
     */
    public function handle(ExperienceCandidate $candidate, FormAdoptExperienceCandidateData $data, User $actor): KnowledgeQaEntry
    {
        if ($candidate->status !== ExperienceCandidateStatus::Pending) {
            throw new BusinessException(__('experience.errors.candidate_already_handled'));
        }

        $knowledgeBase = $candidate->extraction->knowledgeBase;
        if ($knowledgeBase === null) {
            throw new BusinessException(__('experience.errors.qa_knowledge_base_not_found'));
        }

        $entry = CreateKnowledgeQaEntryAction::run(
            $knowledgeBase,
            FormCreateKnowledgeQaEntryData::from([
                'question' => $data->question,
                'similar_questions' => $data->similar_questions,
                'answers' => [$data->answer],
            ]),
            (string) $actor->id,
        );

        $candidate->update([
            'status' => ExperienceCandidateStatus::Adopted,
            'adopted_qa_entry_id' => $entry->id,
            'handled_by_user_id' => $actor->id,
            'handled_at' => now(),
        ]);

        return $entry;
    }

    /**
     * 解析当前应用下的候选并执行采纳，跳回经验提炼页。
     */
    public function asController(Request $request, string $candidate): RedirectResponse
    {
        $model = ExperienceCandidate::query()

            ->findOrFail($candidate);

        /** @var User $actor */
        $actor = $request->user();
        $this->handle($model, FormAdoptExperienceCandidateData::from($request), $actor);

        // 回到来源页（经验结果页），保持当前状态 Tab 与筛选。
        return redirect()->back(fallback: route('app.manage.experience-extraction.results', [
            'extraction' => (string) $model->extraction_id,
        ]));
    }
}
