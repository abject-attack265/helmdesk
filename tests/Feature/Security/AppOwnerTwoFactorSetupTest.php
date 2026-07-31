<?php

use App\Actions\Security\ShowAppOwnerTwoFactorSetupPageAction;
use App\Http\Middleware\EnsureAppOwnerTwoFactorConfirmed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
});

test('首个注册用户进入 Google Authenticator 配置页并可完成绑定', function () {
    $this->post(route('register.store'), [
        'name' => 'firstadmin',
        'email' => 'first-admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('app.owner.two-factor.setup', absolute: false));

    /** @var User $user */
    $user = User::query()->where('email', 'first-admin@example.com')->firstOrFail();

    $this->get(route('app.owner.two-factor.setup'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('app/OwnerTwoFactorSetup')
            ->where('two_factor_enabled', false)
            ->where('two_factor_confirmed', false)
            ->missing('recovery_codes')
        );

    $this->post(route('app.owner.two-factor.enable'))
        ->assertRedirect(route('app.owner.two-factor.setup'));

    $user->refresh();

    $this->get(route('app.owner.two-factor.setup'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('two_factor_enabled', true)
            ->where('two_factor_confirmed', false)
            ->where('qr_code_svg', fn (mixed $value): bool => is_string($value) && $value !== '')
            ->where('manual_setup_key', fn (mixed $value): bool => is_string($value) && $value !== '')
            ->missing('recovery_codes')
        );

    $secret = decrypt($user->two_factor_secret);
    $otp = (new Google2FA)->getCurrentOtp($secret);

    $this->post(route('app.owner.two-factor.confirm'), ['code' => $otp])
        ->assertRedirect(route('app.home'));

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

test('已确认两步验证时初始化页数据不包含密钥与恢复码', function () {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('confirmed-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $props = app(ShowAppOwnerTwoFactorSetupPageAction::class)->handle($user)->toArray();

    expect($props)
        ->toMatchArray([
            'two_factor_enabled' => true,
            'two_factor_confirmed' => true,
            'qr_code_svg' => null,
            'manual_setup_key' => null,
        ])
        ->not->toHaveKey('recovery_codes');
});

test('应用所有者确认两步验证时只接受六位数字验证码', function () {
    $this->post(route('register.store'), [
        'name' => 'firstadmin',
        'email' => 'first-admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    /** @var User $user */
    $user = User::query()->where('email', 'first-admin@example.com')->firstOrFail();

    $this->post(route('app.owner.two-factor.enable'));

    $this->post(route('app.owner.two-factor.confirm'), ['code' => '12ab'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

test('首个管理员暂时跳过两步验证时本次会话可以进入应用', function () {
    $this->post(route('register.store'), [
        'name' => 'firstadmin',
        'email' => 'first-admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->post(route('app.owner.two-factor.skip'))
        ->assertRedirect(route('app.home'));

    expect(session(EnsureAppOwnerTwoFactorConfirmed::SKIP_SESSION_KEY))->toBeTrue();

    $this->get(route('app.home'))
        ->assertRedirect(route('app.dashboard'));
});
