<?php

use App\Data\Dashboard\DashboardPeriodStatsData;
use App\Data\Dashboard\ShowDashboardPagePropsData;
use App\Enums\AiModelPurpose;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Models\AiUsageLog;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationRating;
use App\Models\Membership;
use App\Models\User;
use App\Services\Dashboard\DashboardMetricsBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

/**
 * 以 UTC 时区组装 Dashboard，便于按 UTC 时间戳断言日期分桶。
 */
function buildDashboard(): ShowDashboardPagePropsData
{
    return app(DashboardMetricsBuilder::class)->build('UTC');
}

/**
 * 从周期对比卡中按 key 取出某个周期。
 */
function periodOf(ShowDashboardPagePropsData $props, string $key): DashboardPeriodStatsData
{
    return collect($props->periods)->firstWhere('key', $key);
}

/**
 * 写一条指定时间与 token 的用量记录。
 */
function seedDashboardUsage(Carbon $at, int $input, int $output): void
{
    AiUsageLog::query()->create([
        'ai_model_id' => null,
        'model_name' => 'test-model',
        'purpose' => AiModelPurpose::ReceptionChat,
        'conversation_id' => null,
        'input_tokens' => $input,
        'output_tokens' => $output,
        'created_at' => $at,
    ]);
}

/**
 * 直接写一条评价（绕过提交流程，便于控制时间与归属）。
 */
function seedRating(string $score, Carbon $at): void
{
    ConversationRating::query()->create([
        'conversation_id' => (string) Str::ulid(),
        'score' => $score,
        'channel_type' => 'web',
        'handled_by' => 'ai',
        'rated_at' => $at,
    ]);
}

test('状态快照与周期对比卡按区间聚合', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));
    $app = createSystemSettings();
    $channel = Channel::factory()->create([]);

    // 今日 2 条待接入 + 1 条 AI 处理
    Conversation::factory()->count(2)->create([
        'channel_id' => $channel->id,
        'status' => ConversationStatus::Open,
        'inbox_status' => ConversationInboxStatus::TeammatePending,
        'created_at' => Carbon::parse('2026-06-15 09:00:00'),
    ]);
    Conversation::factory()->create([
        'channel_id' => $channel->id,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'created_at' => Carbon::parse('2026-06-15 08:00:00'),
    ]);
    // 区间内非今日（显式 inbox_status，避免工厂随机值误入待接入计数）
    Conversation::factory()->create([
        'channel_id' => $channel->id,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'created_at' => Carbon::parse('2026-06-12 10:00:00'),
    ]);
    // 区间外
    Conversation::factory()->create([
        'channel_id' => $channel->id,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'created_at' => Carbon::parse('2026-06-01 10:00:00'),
    ]);

    $props = buildDashboard();

    // 状态快照：待接入会话
    expect($props->kpis->pending_conversations)->toBe(2);

    // 周期对比卡：今日 3 条；昨日 0；前 7 日（不含今日）含 06-12 共 1；前 30 日（不含今日）含 06-12、06-01 共 2
    expect(periodOf($props, 'today')->new_conversations)->toBe(3)
        ->and(periodOf($props, 'yesterday')->new_conversations)->toBe(0)
        ->and(periodOf($props, 'prev7')->new_conversations)->toBe(1)
        ->and(periodOf($props, 'prev30')->new_conversations)->toBe(2);

    // 热力图 x 轴：固定 30 天本地日期分桶
    expect($props->daily_dates)->toHaveCount(30)
        ->and($props->daily_dates[29])->toBe('2026-06-15');
});

test('今日周期卡好评率按好评数聚合', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));
    $app = createSystemSettings();

    seedRating('positive', Carbon::parse('2026-06-15 09:00:00'));
    seedRating('positive', Carbon::parse('2026-06-15 10:00:00'));
    seedRating('negative', Carbon::parse('2026-06-15 11:00:00'));

    $props = buildDashboard();

    $today = periodOf($props, 'today');
    expect($today->csat_total)->toBe(3)
        ->and($today->csat_positive_rate)->toBe(66.7);
});

test('周期对比卡按今日/昨日/前 7 日（不含今日）切分人工处理与 token 用量', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));
    $app = createSystemSettings();
    $channel = Channel::factory()->create([]);
    $agent = User::factory()->create();

    // 今日：1 条人工处理（有指派坐席）
    Conversation::factory()->create([
        'channel_id' => $channel->id,
        'assigned_user_id' => $agent->id,
        'created_at' => Carbon::parse('2026-06-15 09:00:00'),
    ]);
    // 昨日：1 条人工处理
    Conversation::factory()->create([
        'channel_id' => $channel->id,
        'assigned_user_id' => $agent->id,
        'created_at' => Carbon::parse('2026-06-14 09:00:00'),
    ]);

    // token：今日 100，昨日 30
    seedDashboardUsage(Carbon::parse('2026-06-15 09:00:00'), 60, 40);
    seedDashboardUsage(Carbon::parse('2026-06-14 09:00:00'), 20, 10);

    $props = buildDashboard();

    // 今日只含 06-15
    expect(periodOf($props, 'today')->human_conversations)->toBe(1)
        ->and(periodOf($props, 'today')->tokens)->toBe(100);
    // 昨日只含 06-14
    expect(periodOf($props, 'yesterday')->human_conversations)->toBe(1)
        ->and(periodOf($props, 'yesterday')->tokens)->toBe(30);
    // 前 7 日不含今日：仅 06-14 计入
    expect(periodOf($props, 'prev7')->human_conversations)->toBe(1)
        ->and(periodOf($props, 'prev7')->tokens)->toBe(30);

    // 坐席绩效：按天接待量与日期分桶对齐（30 天，06-15 与 06-14 各 1）
    expect($props->agent_performance)->toHaveCount(1);
    $row = $props->agent_performance[0];
    $byDate = array_combine($props->daily_dates, $row->daily);
    expect($row->handled)->toBe(2)
        ->and($row->daily)->toHaveCount(30)
        ->and(array_sum($row->daily))->toBe(2)
        ->and($byDate['2026-06-15'])->toBe(1)
        ->and($byDate['2026-06-14'])->toBe(1);
});

test('周期对比卡按查看者时区切分本地日', function () {
    // 上海时间 2026-06-15 10:00（= UTC 02:00）看板：
    // 本地今日始于 UTC 06-14 16:00，本地昨日始于 UTC 06-13 16:00。
    Carbon::setTestNow(Carbon::parse('2026-06-15 02:00:00', 'UTC'));
    $app = createSystemSettings();
    $channel = Channel::factory()->create([]);

    // UTC 06-14 20:00 = 上海 06-15 04:00，属本地今日；按 UTC 切天则会被错算进昨日。
    Conversation::factory()->create([
        'channel_id' => $channel->id,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'created_at' => Carbon::parse('2026-06-14 20:00:00', 'UTC'),
    ]);
    // UTC 06-14 10:00 = 上海 06-14 18:00，属本地昨日。
    Conversation::factory()->create([
        'channel_id' => $channel->id,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'created_at' => Carbon::parse('2026-06-14 10:00:00', 'UTC'),
    ]);

    $props = app(DashboardMetricsBuilder::class)->build('Asia/Shanghai');

    expect(periodOf($props, 'today')->new_conversations)->toBe(1)
        ->and(periodOf($props, 'yesterday')->new_conversations)->toBe(1)
        ->and($props->daily_dates[29])->toBe('2026-06-15');
});

test('坐席按天接待量与合计在非 UTC 时区下自洽', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-15 02:00:00', 'UTC'));
    $app = createSystemSettings();
    $channel = Channel::factory()->create([]);
    $agent = User::factory()->create();
    Membership::query()->create(['user_id' => $agent->id]);

    foreach (['2026-06-14 20:00:00', '2026-06-14 10:00:00'] as $createdAt) {
        Conversation::factory()->create([
            'channel_id' => $channel->id,
            'assigned_user_id' => $agent->id,
            'inbox_status' => ConversationInboxStatus::AiHandling,
            'created_at' => Carbon::parse($createdAt, 'UTC'),
        ]);
    }

    $props = app(DashboardMetricsBuilder::class)->build('Asia/Shanghai');
    $row = collect($props->agent_performance)->firstWhere('user_id', $agent->id);

    // 按本地日分组，按天明细之和与合计保持一致。
    expect($row->handled)->toBe(2)
        ->and(array_sum($row->daily))->toBe(2);

    // 两条分别落在本地 06-15 与 06-14 两个桶。
    $byDate = array_combine($props->daily_dates, $row->daily);
    expect($byDate['2026-06-15'])->toBe(1)
        ->and($byDate['2026-06-14'])->toBe(1);
});
