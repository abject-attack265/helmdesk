<?php

namespace App\Actions\Dashboard;

use App\Data\CurrentUserContextData;
use App\Data\Dashboard\ShowDashboardPagePropsData;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示当前应用仪表板：周期对比、状态快照、按天趋势、占比/分布与坐席绩效。
 */
class ShowDashboardPageAction
{
    use AsAction;

    public function __construct(
        private readonly DashboardMetricsBuilder $builder,
    ) {}

    /**
     * 按应用与查看者时区组装 Dashboard props。
     */
    public function handle(string $timezone): ShowDashboardPagePropsData
    {
        return $this->builder->build($timezone);
    }

    /**
     * 解析当前应用与查看者时区，渲染 Dashboard 页面。
     */
    public function asController(Request $request): Response
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);

        $props = $this->handle(
            timezone: $user->resolvedTimezone(),
        );

        return Inertia::render('Dashboard', $props->toArray());
    }
}
