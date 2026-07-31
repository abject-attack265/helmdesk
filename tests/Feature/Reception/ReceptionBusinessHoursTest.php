<?php

use App\Data\Reception\Config\ReceptionBusinessHoursData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * 构造只启用周一的服务时间配置（其余六天关闭），时区固定 Asia/Shanghai。
 */
function businessHoursOnMonday(string $open, string $close): ReceptionBusinessHoursData
{
    $schedule = [];
    for ($day = 1; $day <= 7; $day++) {
        $schedule[] = [
            'day' => $day,
            'enabled' => $day === 1,
            'open' => $open,
            'close' => $close,
        ];
    }

    return ReceptionBusinessHoursData::fromArray([
        'outside_hours_notice' => '当前不是人工服务时间。',
        'schedule' => $schedule,
        'timezone' => 'Asia/Shanghai',
    ]);
}

/**
 * 取上海时区下指定周一时刻（2026-07-20 是周一）。
 */
function mondayAt(string $time): Carbon
{
    return Carbon::parse("2026-07-20 {$time}", 'Asia/Shanghai');
}

test('结束时间 00:00 表示营业到当天结束', function () {
    // 表单校验按 24:00 放行 09:00-00:00，营业判定必须同样按 24:00 处理，
    // 否则该配置保存成功却全天判定为非营业。
    $hours = businessHoursOnMonday('09:00', '00:00');

    expect($hours->isWithinSchedule(mondayAt('10:00')))->toBeTrue()
        ->and($hours->isWithinSchedule(mondayAt('23:59')))->toBeTrue()
        ->and($hours->isWithinSchedule(mondayAt('08:59')))->toBeFalse();
});

test('常规区间在开始时刻含端点、结束时刻不含端点', function () {
    $hours = businessHoursOnMonday('09:00', '18:00');

    expect($hours->isWithinSchedule(mondayAt('09:00')))->toBeTrue()
        ->and($hours->isWithinSchedule(mondayAt('17:59')))->toBeTrue()
        ->and($hours->isWithinSchedule(mondayAt('18:00')))->toBeFalse();
});

test('未启用的星期一律不营业', function () {
    $hours = businessHoursOnMonday('09:00', '18:00');

    // 2026-07-21 是周二，配置里未启用。
    expect($hours->isWithinSchedule(Carbon::parse('2026-07-21 10:00', 'Asia/Shanghai')))->toBeFalse();
});

test('营业判定按配置时区而非传入时刻的时区', function () {
    $hours = businessHoursOnMonday('09:00', '18:00');

    // UTC 周一 02:00 = 上海周一 10:00，在营业时间内。
    expect($hours->isWithinSchedule(Carbon::parse('2026-07-20 02:00', 'UTC')))->toBeTrue()
        // UTC 周一 17:00 = 上海周二 01:00，周二未启用。
        ->and($hours->isWithinSchedule(Carbon::parse('2026-07-20 17:00', 'UTC')))->toBeFalse();
});
