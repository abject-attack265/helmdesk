<?php

namespace App\Actions\Reception\Plan;

use App\Data\Reception\PageProps\ShowReceptionPlanListPagePropsData;
use App\Data\Reception\ReceptionPlanData;
use App\Data\SimplePaginationData;
use App\Models\ReceptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/** 展示当前应用的接待方案列表。 */
class ShowReceptionPlanIndexPageAction
{
    use AsAction;

    /**
     * 分页查询可用接待方案并组装页面数据。
     */
    public function handle(int $page = 1): ShowReceptionPlanListPagePropsData
    {
        $page = max(1, $page);

        $paginator = ReceptionPlan::query()
            ->withCount(['knowledgeBases', 'integrationGrants'])
            ->latest('updated_at')
            ->latest('id')
            ->paginate(SimplePaginationData::DEFAULT_PER_PAGE, ['*'], 'page', $page);

        $plans = $paginator->getCollection();

        return new ShowReceptionPlanListPagePropsData(
            plan_list: $plans
                ->map(fn (ReceptionPlan $plan): ReceptionPlanData => ReceptionPlanData::fromModel($plan))
                ->values()
                ->all(),
            plan_list_pagination: SimplePaginationData::fromPaginator($paginator),
        );
    }

    /**
     * 渲染接待方案列表。
     */
    public function asController(Request $request): Response
    {
        return Inertia::render('reception/plans/List', $this->handle(
            page: (int) $request->query('page', 1),
        )->toArray());
    }
}
