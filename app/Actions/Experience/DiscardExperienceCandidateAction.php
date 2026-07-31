<?php

namespace App\Actions\Experience;

use App\Enums\ExperienceCandidateStatus;
use App\Exceptions\BusinessException;
use App\Models\ExperienceCandidate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 丢弃一条候选经验：管理员判断无沉淀价值，仅改状态留档，不产生任何知识库内容。
 */
class DiscardExperienceCandidateAction
{
    use AsAction;

    /**
     * 把 Pending 候选标记为 Discarded。
     */
    public function handle(ExperienceCandidate $candidate, User $actor): void
    {
        if ($candidate->status !== ExperienceCandidateStatus::Pending) {
            throw new BusinessException(__('experience.errors.candidate_already_handled'));
        }

        $candidate->update([
            'status' => ExperienceCandidateStatus::Discarded,
            'handled_by_user_id' => $actor->id,
            'handled_at' => now(),
        ]);
    }

    /**
     * 解析当前应用下的候选并执行丢弃，跳回经验提炼页。
     */
    public function asController(Request $request, string $candidate): RedirectResponse
    {
        $model = ExperienceCandidate::query()

            ->findOrFail($candidate);

        /** @var User $actor */
        $actor = $request->user();
        $this->handle($model, $actor);

        // 回到来源页（经验结果页），保持当前状态 Tab 与筛选。
        return redirect()->back(fallback: route('app.manage.experience-extraction.results', [
            'extraction' => (string) $model->extraction_id,
        ]));
    }
}
