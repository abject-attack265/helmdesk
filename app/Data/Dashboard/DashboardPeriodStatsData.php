<?php

namespace App\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * Dashboard 第一栏单个周期（今日/昨日/前 7 日/前 30 日）的汇总指标。
 * key 为周期标识（today/yesterday/prev7/prev30），前端据此渲染标题。
 * csat_positive_rate 为 0-100 的好评率，周期内无评价时为 null。
 */
class DashboardPeriodStatsData extends Data
{
    public function __construct(
        public string $key,
        public int $new_conversations,
        public ?float $csat_positive_rate,
        public int $csat_total,
        public int $human_conversations,
        public int $tokens,
    ) {}
}
