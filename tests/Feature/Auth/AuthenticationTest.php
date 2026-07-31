<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

test('英文访客登录页按浏览器语言渲染', function () {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="en"', false);
});

test('用户可以认证使用登录页面', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated('web');
    $response->assertRedirect(route('inbox', absolute: false));
});

test('未验证用户可以认证但访问受邮箱验证拦截', function () {
    $user = User::factory()->withoutTwoFactor()->unverified()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated('web');
    $response->assertRedirect(route('inbox', absolute: false));

    $this->get(route('dashboard'))
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('用户登录会忽略预期重定向并进入收件箱', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = $this->withSession([
        'url.intended' => '/login',
    ])->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated('web');
    $response->assertRedirect(route('inbox', absolute: false));
});

test('启用双因素认证的用户会被重定向到双因素挑战', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->create([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest('web');
});

test('用户不能认证且无效密码', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest('web');
});

test('用户可以登出', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest('web');
    $response->assertRedirect(route('home'));
});

test('用户可以取消两步验证挑战并切换账号', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ])->assertRedirect(route('two-factor.login'));

    $this->post(route('two-factor.login.cancel'))
        ->assertRedirect(route('login'))
        ->assertSessionMissing('login.id')
        ->assertSessionMissing('login.remember');

    $this->get(route('two-factor.login'))
        ->assertRedirect(route('login'));
});

test('用户会被限流', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
