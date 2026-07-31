<?php

use App\Actions\User\OfflineInactiveInstanceMembersAction;
use App\Enums\UserOnlineStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * 读取成员在应用内的在线状态。
 */
function onlineStatusOf($app, $user): int
{
    return (int) DB::table('memberships')

        ->where('user_id', $user->id)
        ->value('online_status');
}

test('它把超过不活跃阈值仍在线的成员置为离线', function () {
    [$app, $inactive] = createInstanceWithOwner();
    [$activeInstance, $active] = createInstanceWithOwner();
    [$offlineInstance, $alreadyOffline] = createInstanceWithOwner();

    $inactive->membership()->update([
        'online_status' => UserOnlineStatus::Online,
        'last_active_at' => now()->subMinutes(11),
    ]);
    $active->membership()->update([
        'online_status' => UserOnlineStatus::Online,
        'last_active_at' => now()->subMinutes(3),
    ]);
    // 已离线且长期未活动：仍保持离线，不被重复处理。
    $alreadyOffline->membership()->update([
        'online_status' => UserOnlineStatus::Offline,
        'last_active_at' => now()->subHour(),
    ]);

    $offlined = app(OfflineInactiveInstanceMembersAction::class)->handle();

    expect($offlined)->toBe(1)
        ->and(onlineStatusOf($app, $inactive))->toBe(UserOnlineStatus::Offline->value)
        ->and(onlineStatusOf($activeInstance, $active))->toBe(UserOnlineStatus::Online->value)
        ->and(onlineStatusOf($offlineInstance, $alreadyOffline))->toBe(UserOnlineStatus::Offline->value);
});

test('它把从未活动仍在线的成员置为离线', function () {
    [$app, $user] = createInstanceWithOwner();

    $user->membership()->update([
        'online_status' => UserOnlineStatus::Online,
        'last_active_at' => null,
    ]);

    $offlined = app(OfflineInactiveInstanceMembersAction::class)->handle();

    expect($offlined)->toBe(1)
        ->and(onlineStatusOf($app, $user))->toBe(UserOnlineStatus::Offline->value);
});

test('定时命令可执行并完成不活跃成员离线', function () {
    [$app, $user] = createInstanceWithOwner();

    $user->membership()->update([
        'online_status' => UserOnlineStatus::Online,
        'last_active_at' => now()->subMinutes(20),
    ]);

    $this->artisan('teammates:offline-inactive')->assertSuccessful();

    expect(onlineStatusOf($app, $user))->toBe(UserOnlineStatus::Offline->value);
});
