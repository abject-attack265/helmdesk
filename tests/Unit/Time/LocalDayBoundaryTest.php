<?php

use App\Services\Time\LocalDayBoundary;
use Illuminate\Support\Carbon;

test('本地日边界按给定时区换算成 UTC', function () {
    // Asia/Shanghai 恒为 UTC+8：2026-07-17 当天 = UTC 07-16 16:00 起、07-17 16:00 止（不含）。
    expect(LocalDayBoundary::startUtc('2026-07-17', 'Asia/Shanghai')->toDateTimeString())
        ->toBe('2026-07-16 16:00:00')
        ->and(LocalDayBoundary::endUtcExclusive('2026-07-17', 'Asia/Shanghai')->toDateTimeString())
        ->toBe('2026-07-17 16:00:00');
});

test('上界取次日起点，使所选日期当天整天都落在区间内', function () {
    $start = LocalDayBoundary::startUtc('2026-07-17', 'Asia/Shanghai');
    $end = LocalDayBoundary::endUtcExclusive('2026-07-17', 'Asia/Shanghai');

    // 本地当天最后一秒（23:59:59 = UTC 15:59:59）必须在 [start, end) 内。
    $lastSecond = Carbon::parse('2026-07-17 15:59:59', 'UTC');

    expect($lastSecond->gte($start))->toBeTrue()
        ->and($lastSecond->lt($end))->toBeTrue();
});

test('DST 回拨日取本地日期成立的最早时刻', function () {
    // America/Havana 2026-11-01：01:00 CDT 回拨到 00:00 CST，本地 00:00 出现两次
    // （UTC 04:00 的 CDT 那次、UTC 05:00 的 CST 那次），当天真正的起点是较早的 UTC 04:00。
    expect(LocalDayBoundary::startUtc('2026-11-01', 'America/Havana')->toDateTimeString())
        ->toBe('2026-11-01 04:00:00');
});

test('DST 回拨日不会把当天开头的时刻算进前一天', function () {
    $timezone = 'America/Havana';
    // 从回拨之后的当天时刻（本地 10:00 CST）回推当日起点，正是 Dashboard 的取数路径。
    $now = Carbon::parse('2026-11-01 15:00:00', 'UTC')->setTimezone($timezone);
    $todayStartUtc = LocalDayBoundary::startOfDayUtc($now, $timezone);

    // 本地 00:30 CDT 属于 11-01，必须落在当日区间内。
    $earlyEvent = Carbon::parse('2026-11-01 04:30:00', 'UTC');

    expect($todayStartUtc->toDateTimeString())->toBe('2026-11-01 04:00:00')
        ->and($earlyEvent->gte($todayStartUtc))->toBeTrue();
});

test('DST 春季跳变日取顺延后的实际起点', function () {
    // America/Havana 2026-03-08：本地 00:00 直接跳到 01:00 CDT（= UTC 05:00），
    // 不存在的午夜顺延后就是当天真正的起点。
    expect(LocalDayBoundary::startUtc('2026-03-08', 'America/Havana')->toDateTimeString())
        ->toBe('2026-03-08 05:00:00');
});

test('startOfDayUtc 按给定时区而非时刻自身时区判定本地日', function () {
    // UTC 2026-07-16 20:00 在上海已是 07-17 04:00，其所在本地日应为 07-17。
    $at = Carbon::parse('2026-07-16 20:00:00', 'UTC');

    expect(LocalDayBoundary::startOfDayUtc($at, 'Asia/Shanghai')->toDateTimeString())
        ->toBe('2026-07-16 16:00:00');
});
