<?php

namespace App\Data\Reception\Config;

use LogicException;
use Spatie\LaravelData\Data;

/**
 * 接待方案单日人工服务时间。
 */
class ReceptionBusinessHoursDayData extends Data
{
    /**
     * $day 遵循 ISO 周次：1=周一 … 7=周日。
     */
    public function __construct(
        public int $day,
        public bool $enabled,
        public string $open,
        public string $close,
    ) {}

    /**
     * 把 H:i 时间转换为当天分钟数；格式非法或超出取值范围返回 null，供表单校验判定。
     */
    public static function timeToMinutes(string $time): ?int
    {
        if (! preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return $hour * 60 + $minute;
    }

    /**
     * 把结束时间转换为当天分钟数，其中 00:00 表示当天结束，即 24:00。
     *
     * 表单校验与营业判定必须共用此转换，否则「营业到 00:00」两侧口径不一致。
     */
    public static function closeTimeToMinutes(string $time): ?int
    {
        $minutes = self::timeToMinutes($time);

        return $minutes === 0 ? 1440 : $minutes;
    }

    /**
     * 当日开始营业的分钟数；配置已过表单校验，格式非法属数据损坏，直接抛出。
     */
    public function openMinutes(): int
    {
        return self::timeToMinutes($this->open)
            ?? throw new LogicException("Invalid business hours open time: {$this->open}");
    }

    /**
     * 当日结束营业的分钟数（00:00 记为 1440）；配置已过表单校验，格式非法属数据损坏，直接抛出。
     */
    public function closeMinutes(): int
    {
        return self::closeTimeToMinutes($this->close)
            ?? throw new LogicException("Invalid business hours close time: {$this->close}");
    }
}
