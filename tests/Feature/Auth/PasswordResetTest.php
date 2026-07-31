<?php

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('重置密码流程：请求链接、渲染重置页并用有效令牌重置', function () {
    Notification::fake();

    $this->get(route('password.request'))->assertOk();

    $user = User::factory()->create();
    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, QueuedResetPassword::class, function (QueuedResetPassword $notification) use ($user) {
        expect($notification)->toBeInstanceOf(ShouldQueue::class);

        $this->get(route('password.reset', $notification->token))->assertOk();

        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        return true;
    });
});

test('已注册与未注册邮箱请求重置链接会获得相同对外响应', function () {
    Notification::fake();

    $user = User::factory()->create();
    $from = route('password.request');
    $message = __('passwords.sent');

    $registeredResponse = $this->from($from)
        ->post(route('password.email'), ['email' => $user->email]);

    $registeredResponse
        ->assertRedirect($from)
        ->assertSessionHas('status', $message)
        ->assertSessionHasNoErrors();

    $missingResponse = $this->from($from)
        ->post(route('password.email'), ['email' => 'missing@example.com']);

    $missingResponse
        ->assertRedirect($from)
        ->assertSessionHas('status', $message)
        ->assertSessionHasNoErrors();

    expect($registeredResponse->getStatusCode())->toBe($missingResponse->getStatusCode())
        ->and($registeredResponse->headers->get('Location'))->toBe($missingResponse->headers->get('Location'));

    Notification::assertSentTo($user, QueuedResetPassword::class);
    Notification::assertCount(1);
});

test('未注册邮箱的密码重置JSON响应与已注册邮箱一致', function () {
    Notification::fake();

    $user = User::factory()->create();
    $message = __('passwords.sent');

    $this->postJson(route('password.email'), ['email' => $user->email])
        ->assertOk()
        ->assertExactJson(['message' => $message]);

    $this->postJson(route('password.email'), ['email' => 'missing@example.com'])
        ->assertOk()
        ->assertExactJson(['message' => $message]);
});

test('密码不能被重置并使用无效令牌', function () {
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertSessionHasErrors('email');
});
