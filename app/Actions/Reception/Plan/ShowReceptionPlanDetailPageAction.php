<?php

namespace App\Actions\Reception\Plan;

use App\Data\EnumOptionData;
use App\Data\Reception\PageProps\ShowReceptionPlanDetailPagePropsData;
use App\Data\Reception\ReceptionPlanData;
use App\Data\Reception\ServiceScenario\PlanIntegrationOptionData;
use App\Data\Reception\ServiceScenario\PlanKnowledgeBaseOptionData;
use App\Enums\IntegrationSyncStatus;
use App\Enums\ReceptionPersonaTone;
use App\Models\Integration;
use App\Models\KnowledgeBase;
use App\Models\ReceptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染接待方案详情（编辑）页（Detail.vue）。
 * 下发单个方案的完整配置（含服务场景 / 方案级 KB / MCP 工具）及表单所需的全部选项；
 * 选项一次性下发，避免打开服务场景 / KB / 集成表单时再单独请求。保存即发布。
 */
class ShowReceptionPlanDetailPageAction
{
    use AsAction;

    /**
     * 组装详情页 props：选中方案 + 表单选项集合。
     */
    public function handle(ReceptionPlan $plan): ShowReceptionPlanDetailPagePropsData
    {
        return new ShowReceptionPlanDetailPagePropsData(
            plan: ReceptionPlanData::fromModelDetailed($plan),
            persona_tone_options: EnumOptionData::fromCases(ReceptionPersonaTone::cases()),
            knowledge_base_options: $this->buildKnowledgeBaseOptions(),
            integration_options: $this->buildIntegrationOptions(),
        );
    }

    /**
     * Controller 入口：鉴权 + 限定本应用后渲染详情页。
     */
    public function asController(Request $request, string $plan): Response
    {

        $planModel = ReceptionPlan::query()

            ->findOrFail($plan);

        return Inertia::render('reception/plans/Detail', $this->handle($planModel)->toArray());
    }

    /**
     * 加载应用可见 KB，供方案级 KB 多选项使用。
     *
     * @return list<PlanKnowledgeBaseOptionData>
     */
    private function buildKnowledgeBaseOptions(): array
    {
        return KnowledgeBase::query()

            ->orderBy('name')
            ->get()
            ->map(fn (KnowledgeBase $kb): PlanKnowledgeBaseOptionData => PlanKnowledgeBaseOptionData::fromModel($kb))
            ->all();
    }

    /**
     * 加载应用可用集成（上次同步未失败、endpoint 非空），连同各自启用且未下线的工具，
     * 供方案级集成授权多选项使用。
     *
     * @return list<PlanIntegrationOptionData>
     */
    private function buildIntegrationOptions(): array
    {
        return Integration::query()

            ->where('last_sync_status', '!=', IntegrationSyncStatus::Failed)
            ->whereNotNull('endpoint_url')
            ->where('endpoint_url', '!=', '')
            ->with(['tools' => function ($query): void {
                $query->where('is_enabled', true)->whereNull('removed_at')->orderBy('name');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Integration $integration): PlanIntegrationOptionData => PlanIntegrationOptionData::fromModel($integration))
            ->all();
    }
}
