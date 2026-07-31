<?php

namespace App\Actions\Experience;

use App\Actions\KnowledgeBase\BuildKnowledgeBaseSidebarDataAction;
use App\Data\EnumOptionData;
use App\Data\Experience\ExperienceKnowledgeBaseData;
use App\Data\Experience\ListExperienceExtractionItemData;
use App\Data\Experience\ShowExperienceExtractionListPagePropsData;
use App\Data\SimplePaginationData;
use App\Enums\ExperienceCandidateStatus;
use App\Enums\ExperienceExtractionStatus;
use App\Enums\KnowledgeBaseCategory;
use App\Models\ExperienceExtraction;
use App\Models\KnowledgeBase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染「经验提炼」任务列表页：绑定问答知识库下按时间倒序分页的提炼运行历史，支持状态筛选。
 */
class ShowExperienceExtractionListPageAction
{
    use AsAction;

    /**
     * 组装绑定问答库的任务列表 props。
     */
    public function handle(KnowledgeBase $knowledgeBase, ?ExperienceExtractionStatus $status, int $page): ShowExperienceExtractionListPagePropsData
    {
        $query = ExperienceExtraction::query()
            ->where('knowledge_base_id', $knowledgeBase->id)
            ->with('triggeredBy')
            ->withCount(['candidates as pending_candidates_count' => function ($q): void {
                $q->where('status', ExperienceCandidateStatus::Pending);
            }])
            ->latest('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        $paginator = $query->paginate(SimplePaginationData::DEFAULT_PER_PAGE, ['*'], 'page', max(1, $page));

        return new ShowExperienceExtractionListPagePropsData(
            sidebar: BuildKnowledgeBaseSidebarDataAction::run(),
            knowledge_base: ExperienceKnowledgeBaseData::fromModel($knowledgeBase),
            extractions: $paginator->getCollection()
                ->map(static fn (ExperienceExtraction $e): ListExperienceExtractionItemData => ListExperienceExtractionItemData::fromModel($e))
                ->all(),
            extractions_pagination: SimplePaginationData::fromPaginator($paginator),
            status_options: EnumOptionData::fromCases(ExperienceExtractionStatus::cases()),
            current_status: $status,
        );
    }

    /**
     * 解析当前应用下的问答知识库与状态筛选参数并渲染任务列表页。
     */
    public function asController(Request $request, string $knowledgeBase): Response
    {
        $model = KnowledgeBase::query()

            ->where('category', KnowledgeBaseCategory::Qa)
            ->findOrFail($knowledgeBase);

        $status = ExperienceExtractionStatus::tryFrom((string) $request->query('status'));
        $page = (int) $request->query('page', '1');

        return Inertia::render('experiences/Index', $this->handle($model, $status, $page));
    }
}
