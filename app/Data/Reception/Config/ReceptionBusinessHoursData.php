<?php

namespace App\Data\Reception\Config;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

/**
 * 接待方案人工服务时间配置。
 */
class ReceptionBusinessHoursData extends Data
{
    /**
     * @param  list<ReceptionBusinessHoursDayData>  $schedule
     */
    public function __construct(
        public string $outside_hours_notice,
        public array $schedule,
        public string $timezone,
    ) {}

    /**
     * 从完整配置数组创建人工服务时间配置。
     *
     * @param  array{outside_hours_notice:string,schedule:list<array{day:int,enabled:bool,open:string,close:string}>,timezone:string}  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            outside_hours_notice: trim($raw['outside_hours_notice']),
            schedule: array_map(
                static fn (array $day): ReceptionBusinessHoursDayData => ReceptionBusinessHoursDayData::from($day),
                $raw['schedule'],
            ),
            timezone: $raw['timezone'],
        );
    }

    /**
     * 判断给定时刻是否在人工服务时间内。
     */
    public function isWithinSchedule(?Carbon $at = null): bool
    {
        $now = ($at ?? Carbon::now())->copy()->setTimezone($this->timezone);
        $isoDay = (int) $now->isoFormat('E');
        $currentMinutes = $now->hour * 60 + $now->minute;

        foreach ($this->schedule as $day) {
            if ($day->day !== $isoDay || ! $day->enabled) {
                continue;
            }

            if ($currentMinutes >= $day->openMinutes() && $currentMinutes < $day->closeMinutes()) {
                return true;
            }
        }

        return false;
    }
}
