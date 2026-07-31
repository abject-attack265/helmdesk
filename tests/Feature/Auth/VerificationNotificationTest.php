<?php

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('验证通知仅对未验证的普通用户发送', function () {
    Notification::fake();

    // 未验证普通用户：发送队列化验证通知并回首页
    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));
    Notification::assertSentTo(
        $unverified,
        QueuedVerifyEmail::class,
        fn (QueuedVerifyEmail $notification) => $notification instanceof ShouldQueue,
    );

    // 已验证用户：不发送，直接回仪表盘
    $verified = User::factory()->create();
    $this->actingAs($verified)
        ->post(route('verification.send'))
        ->assertRedirect(route('dashboard', absolute: false));

    // 全程只对未验证普通用户发过一次
    Notification::assertSentTimes(QueuedVerifyEmail::class, 1);
});
