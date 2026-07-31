<?php

use App\Actions\User\TouchSystemUserLastActiveAtAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('它刷新过期应用成员最后活跃时间戳', function () {
    [$app, $user] = createInstanceWithOwner();
    $previousLastActiveAt = now()->subMinutes(10);

    $user->membership()->update([
        'last_active_at' => $previousLastActiveAt,
    ]);

    TouchSystemUserLastActiveAtAction::run((string) $user->id);

    $updatedLastActiveAt = DB::table('memberships')

        ->where('user_id', $user->id)
        ->value('last_active_at');

    expect($updatedLastActiveAt)->not->toBeNull()
        ->and(Carbon::parse((string) $updatedLastActiveAt)->isAfter($previousLastActiveAt))->toBeTrue();
});

test('它跳过刷新最近活跃应用成员', function () {
    [$app, $user] = createInstanceWithOwner();
    $recentLastActiveAt = now()->subSeconds(30)->startOfSecond();

    $user->membership()->update([
        'last_active_at' => $recentLastActiveAt,
    ]);

    TouchSystemUserLastActiveAtAction::run((string) $user->id);

    $updatedLastActiveAt = DB::table('memberships')

        ->where('user_id', $user->id)
        ->value('last_active_at');

    expect(Carbon::parse((string) $updatedLastActiveAt)->toDateTimeString())
        ->toBe($recentLastActiveAt->toDateTimeString());
});
