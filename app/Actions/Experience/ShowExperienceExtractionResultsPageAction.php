<?php

namespace App\Actions\Experience;

use App\Actions\KnowledgeBase\BuildKnowledgeBaseSidebarDataAction;
use App\Data\Experience\ExperienceExtractionData;
use App\Data\Experience\ListExperienceCandidateItemData;
use App\Data\Experience\ShowExperienceExtractionResultsPagePropsData;
use App\Enums\ExperienceCandidateStatus;
use App\Models\ExperienceCandidate;
use App\Models\ExperienceExtraction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染「提炼任务经验结果」页：该任务产出的候选经验审核后台（按状态筛选、内联润色采纳）。
 */
class ShowExperienceExtractionResultsPageAction
{
    use AsAction;

    /**
     * 组装任务的候选经验与状态计数 props；采纳目标即任务绑定的问答库。
     */
    public function handle(ExperienceExtraction $extraction, ExperienceCandidateStatus $activeStatus): ShowExperienceExtractionResultsPagePropsData
    {
        $candidates = $extraction->candidates()
            ->where('status', $activeStatus)
            ->orderByDesc('conversation_count')
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn (ExperienceCandidate $c): ListExperienceCandidateItemData => ListExperienceCandidateItemData::fromModel($c))
            ->all();

        $statusCounts = $extraction->candidates()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $counts = [];
        foreach (ExperienceCandidateStatus::cases() as $status) {
            $counts[$status->value] = (int) ($statusCounts[$status->value] ?? 0);
        }

        return new ShowExperienceExtractionResultsPagePropsData(
            sidebar: BuildKnowledgeBaseSidebarDataAction::run(),
            extraction: ExperienceExtractionData::fromModel($extraction),
            candidates: $candidates,
            status_counts: $counts,
            active_status: $activeStatus->value,
        );
    }

    /**
     * 解析当前应用下的任务与状态筛选参数并渲染页面。
     */
    public function asController(Request $request, string $extraction): Response
    {
        $model = ExperienceExtraction::query()

            ->with('knowledgeBase')
            ->findOrFail($extraction);

        $activeStatus = ExperienceCandidateStatus::tryFrom((string) $request->query('status')) ?? ExperienceCandidateStatus::Pending;

        return Inertia::render('experiences/Results', $this->handle($model, $activeStatus));
    }
}
