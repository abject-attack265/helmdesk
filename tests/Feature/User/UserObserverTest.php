<?php

use App\Models\CannedReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    DB::rollBack();
});

afterEach(function (): void {
    DB::table('canned_replies')->delete();
    DB::table('users')->delete();
    DB::beginTransaction();
});

test('个人快捷回复必须在事务中写入', function () {
    $user = User::factory()->create();

    expect(fn () => CannedReply::factory()->ownedBy($user)->create())
        ->toThrow(LogicException::class)
        ->and(CannedReply::query()->count())->toBe(0);

    $reply = DB::transaction(fn () => CannedReply::factory()->ownedBy($user)->create());

    expect($reply->user_id)->toBe($user->id);
});

test('事务外物理删除用户时拒绝修改用户和个人快捷回复', function () {
    $user = User::factory()->create();
    $reply = DB::transaction(fn () => CannedReply::factory()->ownedBy($user)->create());

    expect(fn () => $user->forceDelete())
        ->toThrow(LogicException::class)
        ->and(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and(CannedReply::query()->whereKey($reply->id)->exists())->toBeTrue();
});

test('事务内物理删除用户时清理个人快捷回复并保留应用共享回复', function () {
    $user = User::factory()->create();
    $personalReply = DB::transaction(fn () => CannedReply::factory()->ownedBy($user)->create());
    $sharedReply = CannedReply::factory()->create(['user_id' => null]);

    DB::transaction(fn () => $user->forceDelete());

    expect(User::withTrashed()->whereKey($user->id)->exists())->toBeFalse()
        ->and(CannedReply::withTrashed()->findOrFail($personalReply->id)->trashed())->toBeTrue()
        ->and(CannedReply::query()->whereKey($sharedReply->id)->exists())->toBeTrue();
});
