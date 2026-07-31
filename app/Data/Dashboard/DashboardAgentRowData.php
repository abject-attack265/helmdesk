<?php

namespace App\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * 坐席绩效表的单行：近 30 天接待量合计、好评率（0-100，无评价时为 null）与按天接待量。
 * daily 按 daily_dates 相同的日期分桶顺序排列，用于绘制按天接待量热力图。
 */
class DashboardAgentRowData extends Data
{
    public function __construct(
        public string $user_id,
        public string $name,
        public int $handled,
        public ?float $positive_rate,
        /** @var int[] */
        public array $daily,
    ) {}
}
