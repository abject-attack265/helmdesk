<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('未验证用户不能访问仪表盘在验证邮箱前', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('邮箱可以验证', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

test('邮箱验证链接签名无效时不验证', function () {
    Event::fake();

    // 错误的邮箱 hash
    $badHashUser = User::factory()->unverified()->create();
    $this->actingAs($badHashUser)->get(URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $badHashUser->id, 'hash' => sha1('wrong-email')]
    ));
    expect($badHashUser->fresh()->hasVerifiedEmail())->toBeFalse();

    // 错误的用户 ID
    $badIdUser = User::factory()->unverified()->create();
    $this->actingAs($badIdUser)->get(URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => 123, 'hash' => sha1($badIdUser->email)]
    ));
    expect($badIdUser->fresh()->hasVerifiedEmail())->toBeFalse();

    Event::assertNotDispatched(Verified::class);
});

test('已验证用户访问验证入口直接跳转且不重复触发事件', function () {
    Event::fake();

    // 访问验证提示页 → 跳回仪表盘
    $noticeUser = User::factory()->create();
    $this->actingAs($noticeUser)->get(route('verification.notice'))
        ->assertRedirect(route('dashboard', absolute: false));

    // 再次访问验证链接 → 跳 ?verified=1，且不再触发验证事件
    $linkUser = User::factory()->create();
    $this->actingAs($linkUser)->get(URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $linkUser->id, 'hash' => sha1($linkUser->email)]
    ))->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    expect($linkUser->fresh()->hasVerifiedEmail())->toBeTrue();

    Event::assertNotDispatched(Verified::class);
});
