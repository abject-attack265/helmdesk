<?php

namespace App\Data\Dashboard;

use Spatie\LaravelData\Data;

/**
 * Dashboard 当前状态快照指标卡数值（与周期无关的实时快照）。
 */
class DashboardKpisData extends Data
{
    public function __construct(
        public int $pending_conversations,
        public int $online_agents,
        public int $knowledge_documents,
    ) {}
}
