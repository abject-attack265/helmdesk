<?php

use App\Services\Reception\ReceptionActivityRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::store()->flush();
    Carbon::setTestNow('2026-07-28 12:00:00.000');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('多个接待来源独立持有活动租约', function () {
    $registry = app(ReceptionActivityRegistry::class);

    expect($registry->renewOrdered('conv-1', 'teammate:page-1', 8000, 1))->toBeTrue();
    $teammateActivity = $registry->current('conv-1');
    $registry->renew('conv-1', 'ai:turn:turn-1', 210000);

    $aiActivity = $registry->current('conv-1');
    expect($aiActivity->active)->toBeTrue()
        ->and($aiActivity->hold_ms)->toBe(210000)
        ->and($aiActivity->revision)->toBeGreaterThan($teammateActivity->revision);
    $registry->release('conv-1', 'ai:turn:turn-1');

    $remainingActivity = $registry->current('conv-1');
    expect($remainingActivity->active)->toBeTrue()
        ->and($remainingActivity->hold_ms)->toBe(8000)
        ->and($remainingActivity->revision)->toBeGreaterThan($aiActivity->revision);
});

test('人工活动释放后拒绝乱序到达且顺序号较小的续期', function () {
    $registry = app(ReceptionActivityRegistry::class);

    expect($registry->renewOrdered('conv-1', 'teammate:page-1', 8000, 1))->toBeTrue()
        ->and($registry->releaseOrdered('conv-1', 'teammate:page-1', 3))->toBeTrue()
        ->and($registry->renewOrdered('conv-1', 'teammate:page-1', 8000, 2))->toBeFalse()
        ->and($registry->current('conv-1')->active)->toBeFalse();
});

test('过期活动不会随访客状态回源', function () {
    $registry = app(ReceptionActivityRegistry::class);
    expect($registry->renewOrdered('conv-1', 'teammate:page-1', 8000, 1))->toBeTrue();

    Carbon::setTestNow('2026-07-28 12:00:08.001');

    $expiredActivity = $registry->current('conv-1');

    expect($expiredActivity->active)->toBeFalse()
        ->and($expiredActivity->revision)->toBeGreaterThan(0);
});
