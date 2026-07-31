<?php

namespace App\Services\Time;

use Illuminate\Support\Carbon;

/**
 * 把「用户看到的本地日历日」换算成 UTC 时刻，供按 UTC 存储的时间列做区间筛选与按日聚合。
 *
 * 时间列一律存 UTC，而用户选的日期、看到的日期都是自己时区下的日历日，两者之间的换算必须显式做，
 * 否则 UTC+8 用户筛「今天」实际取到的是本地 08:00 到次日 07:59。
 *
 * 边界一律从日期串构造，不要用 $instant->startOfDay()：DST 回拨日的本地 00:00 出现两次，
 * 从日期串构造取的是较早那次（当天真正的起点），而在当天较晚时刻上调 startOfDay() 取的是较晚那次，
 * 会把当天开头的记录算进前一天。
 */
class LocalDayBoundary
{
    /**
     * 本地日期（Y-m-d）的最早时刻，返回 UTC。
     */
    public static function startUtc(string $date, string $timezone): Carbon
    {
        // '!' 让未被格式覆盖的字段（时分秒、微秒）归零，否则 PHP 会用当前系统时间填充。
        return Carbon::createFromFormat('!Y-m-d', $date, $timezone)->utc();
    }

    /**
     * 本地日期次日的最早时刻，返回 UTC；用作左闭右开区间 [startUtc, endUtcExclusive) 的上界。
     *
     * 取次日起点而非当日 23:59:59，避免依赖时间列的精度。
     */
    public static function endUtcExclusive(string $date, string $timezone): Carbon
    {
        $nextDate = Carbon::createFromFormat('!Y-m-d', $date, 'UTC')->addDay()->toDateString();

        return self::startUtc($nextDate, $timezone);
    }

    /**
     * 给定时刻所在本地日的最早时刻，返回 UTC。
     */
    public static function startOfDayUtc(Carbon $at, string $timezone): Carbon
    {
        $localDate = $at->copy()->setTimezone($timezone)->format('Y-m-d');

        return self::startUtc($localDate, $timezone);
    }
}
