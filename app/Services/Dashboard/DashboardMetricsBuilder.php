<?php

namespace App\Services\Dashboard;

use App\Data\Dashboard\DashboardAgentRowData;
use App\Data\Dashboard\DashboardKpisData;
use App\Data\Dashboard\DashboardPeriodStatsData;
use App\Data\Dashboard\ShowDashboardPagePropsData;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationRatingScore;
use App\Enums\ConversationStatus;
use App\Enums\UserOnlineStatus;
use App\Models\AiUsageLog;
use App\Models\Conversation;
use App\Models\ConversationRating;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Time\LocalDayBoundary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 组装应用 Dashboard 的全部指标：周期对比、状态快照、坐席绩效。
 *
 * 第一栏按四个周期（今日/昨日/前 7 日/前 30 日，后三者不含今日）对比核心指标；
 * 坐席绩效按近 30 天聚合，按天接待量在应用查看者时区下分组，并按完整日期区间补零。
 */
class DashboardMetricsBuilder
{
    private const int AGENT_LIMIT = 10;

    /**
     * 坐席绩效按天聚合的天数窗口。
     */
    private const int TREND_DAYS = 30;

    /**
     * 按时区组装 Dashboard props。
     */
    public function build(string $timezone): ShowDashboardPagePropsData
    {
        $now = Carbon::now($timezone);
        $todayStartUtc = LocalDayBoundary::startOfDayUtc($now, $timezone);

        $trendStartUtc = LocalDayBoundary::startOfDayUtc($now->copy()->subDays(self::TREND_DAYS - 1), $timezone);
        $dates = $this->dateBuckets($now, self::TREND_DAYS);

        return new ShowDashboardPagePropsData(
            periods: $this->periods($now, $timezone, $todayStartUtc),
            kpis: $this->kpis(),
            daily_dates: $dates,
            agent_performance: $this->agentPerformance($trendStartUtc, $timezone, $dates),
        );
    }

    /**
     * 第一栏四个周期的核心指标对比（今日 / 昨日 / 前 7 日 / 前 30 日，后三者均不含今日）。
     *
     * @return list<DashboardPeriodStatsData>
     */
    private function periods(Carbon $now, string $timezone, Carbon $todayStartUtc): array
    {
        $yesterdayStartUtc = LocalDayBoundary::startOfDayUtc($now->copy()->subDay(), $timezone);
        $prev7StartUtc = LocalDayBoundary::startOfDayUtc($now->copy()->subDays(7), $timezone);
        $prev30StartUtc = LocalDayBoundary::startOfDayUtc($now->copy()->subDays(30), $timezone);

        return [
            $this->periodStats('today', $todayStartUtc, null),
            $this->periodStats('yesterday', $yesterdayStartUtc, $todayStartUtc),
            $this->periodStats('prev7', $prev7StartUtc, $todayStartUtc),
            $this->periodStats('prev30', $prev30StartUtc, $todayStartUtc),
        ];
    }

    /**
     * 单个周期 [startUtc, endUtc) 的核心指标；endUtc 为 null 表示开区间（取至当前）。
     */
    private function periodStats(string $key, Carbon $startUtc, ?Carbon $endUtc): DashboardPeriodStatsData
    {
        $newConversations = $this->bound(
            Conversation::query(),
            'created_at', $startUtc, $endUtc
        )->count();

        $humanConversations = $this->bound(
            Conversation::query()->whereNotNull('assigned_user_id'),
            'created_at', $startUtc, $endUtc
        )->count();

        $tokens = (int) $this->bound(
            AiUsageLog::query(),
            'created_at', $startUtc, $endUtc
        )->sum(DB::raw('input_tokens + output_tokens'));

        $ratings = $this->bound(
            ConversationRating::query(),
            'rated_at', $startUtc, $endUtc
        )
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN score = ? THEN 1 ELSE 0 END) as positive', [ConversationRatingScore::Positive])
            ->first();
        $csatTotal = (int) ($ratings->total ?? 0);
        $csatPositive = (int) ($ratings->positive ?? 0);

        return new DashboardPeriodStatsData(
            key: $key,
            new_conversations: $newConversations,
            csat_positive_rate: $csatTotal > 0 ? round($csatPositive / $csatTotal * 100, 1) : null,
            csat_total: $csatTotal,
            human_conversations: $humanConversations,
            tokens: $tokens,
        );
    }

    /**
     * 给查询加上 [startUtc, endUtc) 半开时间区间约束；endUtc 为 null 时只限制下界。
     */
    private function bound(Builder $query, string $column, Carbon $startUtc, ?Carbon $endUtc): Builder
    {
        $query->where($column, '>=', $startUtc);

        if ($endUtc !== null) {
            $query->where($column, '<', $endUtc);
        }

        return $query;
    }

    /**
     * 生成区间内每个本地日期（Y-m-d），用于趋势补零。
     *
     * @return list<string>
     */
    private function dateBuckets(Carbon $now, int $days): array
    {
        $dates = [];
        $cursor = $now->copy()->subDays($days - 1)->startOfDay();
        for ($i = 0; $i < $days; $i++) {
            $dates[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * 与周期无关的实时状态快照：待接入会话、在线坐席、知识库文档。
     */
    private function kpis(): DashboardKpisData
    {
        $pending = Conversation::query()

            ->where('status', ConversationStatus::Open)
            ->where('inbox_status', ConversationInboxStatus::TeammatePending)
            ->count();

        $onlineAgents = DB::table('memberships')
            ->where('online_status', UserOnlineStatus::Online)
            ->count();

        $knowledgeDocuments = KnowledgeDocument::query()

            ->count();

        return new DashboardKpisData(
            pending_conversations: $pending,
            online_agents: $onlineAgents,
            knowledge_documents: $knowledgeDocuments,
        );
    }

    /**
     * 坐席绩效：区间内各坐席接待量合计、好评率与按天接待量，按接待量取前若干。
     *
     * @param  list<string>  $dates
     * @return list<DashboardAgentRowData>
     */
    private function agentPerformance(Carbon $rangeStartUtc, string $timezone, array $dates): array
    {
        $handled = Conversation::query()

            ->where('created_at', '>=', $rangeStartUtc)
            ->whereNotNull('assigned_user_id')
            ->selectRaw('assigned_user_id, COUNT(*) as handled')
            ->groupBy('assigned_user_id')
            ->orderByDesc('handled')
            ->limit(self::AGENT_LIMIT)
            ->get()
            ->keyBy('assigned_user_id');

        if ($handled->isEmpty()) {
            return [];
        }

        $userIds = $handled->keys()->all();

        $ratings = ConversationRating::query()

            ->where('rated_at', '>=', $rangeStartUtc)
            ->whereIn('assigned_user_id', $userIds)
            ->selectRaw('assigned_user_id, COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN score = ? THEN 1 ELSE 0 END) as positive', [ConversationRatingScore::Positive])
            ->groupBy('assigned_user_id')
            ->get()
            ->keyBy('assigned_user_id');

        $daily = $this->agentDailyHandled($rangeStartUtc, $timezone, $userIds);

        $names = User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        return collect($userIds)
            ->map(function (string $userId) use ($handled, $ratings, $names, $daily, $dates): DashboardAgentRowData {
                $rating = $ratings->get($userId);
                $total = (int) ($rating->total ?? 0);
                $byDay = $daily[$userId] ?? [];

                return new DashboardAgentRowData(
                    user_id: $userId,
                    name: (string) ($names[$userId] ?? '—'),
                    handled: (int) $handled->get($userId)->handled,
                    positive_rate: $total > 0 ? round((int) $rating->positive / $total * 100, 1) : null,
                    daily: array_map(static fn (string $date): int => $byDay[$date] ?? 0, $dates),
                );
            })
            ->all();
    }

    /**
     * 各坐席按本地日期分组的接待量，索引为 [user_id][Y-m-d] => 数量。
     *
     * @param  list<string>  $userIds
     * @return array<string, array<string, int>>
     */
    private function agentDailyHandled(Carbon $rangeStartUtc, string $timezone, array $userIds): array
    {
        $daily = [];
        foreach (Conversation::query()
            ->where('created_at', '>=', $rangeStartUtc)
            ->whereIn('assigned_user_id', $userIds)
            ->select(['id', 'assigned_user_id', 'created_at'])
            ->lazyById(1000, 'id') as $row) {
            $userId = (string) $row->assigned_user_id;
            $day = $row->created_at->copy()->setTimezone($timezone)->format('Y-m-d');
            $daily[$userId][$day] = ($daily[$userId][$day] ?? 0) + 1;
        }

        return $daily;
    }
}
