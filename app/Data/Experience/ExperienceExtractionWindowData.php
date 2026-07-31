<?php

namespace App\Data\Experience;

use App\Services\Time\LocalDayBoundary;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * 经验提炼的会话时间窗口（日期粒度、按会话关闭时间取）。
 *
 * 提炼以「联系人在窗口内的全部已关闭会话」为单位，跨度上限把单次 LLM 输入规模钉死。
 * 「创建提炼任务」页回显与触发运行共用这套归一，保证页面看到的会话集合就是实际送去提炼的集合。
 *
 * from / to 是查看者时区下的本地日历日；换算成 UTC 时刻时须传入同一时区，
 * 否则 UTC+8 用户选「今天」实际取到的是本地 08:00 至次日 07:59 的会话。
 */
class ExperienceExtractionWindowData extends Data
{
    /** 窗口最大跨度（天，含首尾）。 */
    public const int MAX_WINDOW_DAYS = 31;

    /**
     * @param  string  $from  起始日期（Y-m-d）
     * @param  string  $to  截止日期（Y-m-d）
     */
    public function __construct(
        public string $from,
        public string $to,
    ) {}

    /**
     * 归一化窗口：缺省取 $timezone 下最近 MAX_WINDOW_DAYS 天，超出跨度上限或首尾颠倒时收敛到合法区间。
     */
    public static function normalize(?Carbon $from, ?Carbon $to, string $timezone): self
    {
        $end = ($to ?? Carbon::now($timezone))->copy()->startOfDay();
        $start = ($from ?? $end->copy()->subDays(self::MAX_WINDOW_DAYS - 1))->copy()->startOfDay();

        if ($start->gt($end)) {
            $start = $end->copy();
        }

        $earliest = $end->copy()->subDays(self::MAX_WINDOW_DAYS - 1);
        if ($start->lt($earliest)) {
            $start = $earliest;
        }

        return new self(
            from: $start->toDateString(),
            to: $end->toDateString(),
        );
    }

    /**
     * 窗口起始时刻（UTC）：起始日在 $timezone 下的最早时刻，闭区间下界。
     */
    public function startsAt(string $timezone): Carbon
    {
        return LocalDayBoundary::startUtc($this->from, $timezone);
    }

    /**
     * 窗口截止时刻（UTC）：截止日次日在 $timezone 下的最早时刻，开区间上界，使截止日当天整天都在窗口内。
     */
    public function endsAtExclusive(string $timezone): Carbon
    {
        return LocalDayBoundary::endUtcExclusive($this->to, $timezone);
    }
}
