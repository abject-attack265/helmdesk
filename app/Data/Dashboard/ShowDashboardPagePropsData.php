<?php

namespace App\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * 应用 Dashboard 页面 props。
 * 消费方：resources/js/pages/Dashboard.vue（周期对比卡 + 状态快照卡 + 坐席绩效热力图）。
 * daily_dates 为近 30 天的本地日期分桶（Y-m-d），与各坐席 agent_performance.daily 同序，作为热力图 x 轴。
 */
class ShowDashboardPagePropsData extends Data
{
    public function __construct(
        /** @var DashboardPeriodStatsData[] */
        public array $periods,
        public DashboardKpisData $kpis,
        /** @var string[] */
        public array $daily_dates,
        /** @var DashboardAgentRowData[] */
        public array $agent_performance,
    ) {}
}
