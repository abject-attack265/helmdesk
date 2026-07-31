<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\AiProvider;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('首个注册用户自动成为管理员、跳过邮箱验证并进入两步验证配置', function () {
    Notification::fake();

    $this->get(route('register'))->assertOk();

    $response = $this->withSession([
        'url.intended' => '/login',
        'password_hash_web' => 'stale-password-baseline',
    ])->post(route('register.store'), [
        'name' => 'firstuser',
        'email' => 'first@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(Auth::guard('web')->check())->toBeTrue();
    expect($this->app['session']->get('password_hash_web'))->toBeNull();

    $user = User::query()->findOrFail(Auth::guard('web')->id());
    $app = systemSettings();
    expect($user->membership)->not->toBeNull()
        ->and($app->owner_id)->toBe($user->id)
        ->and($app->name)->toBe('HelmDesk')
        ->and($app->registration_enabled)->toBeFalse()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        // 注册不预置任何 AI 供应商
        ->and(AiProvider::query()->exists())->toBeFalse();

    // 首个管理员已直接验证邮箱，不发送验证邮件。
    Notification::assertNotSentTo($user, QueuedVerifyEmail::class);

    // 忽略 intended，首个管理员先进入两步验证配置页。
    $response->assertRedirect(route('app.owner.two-factor.setup', absolute: false));
    $this->get(route('dashboard'))->assertRedirect(route('app.owner.two-factor.setup', absolute: false));
});

test('系统管理员可以关闭自主注册', function () {
    [$settings, $owner] = createInstanceWithOwner([], ['registration_enabled' => true]);

    $this->actingAs($owner)
        ->put(route('app.manage.system.settings.update'), [
            'name' => 'HelmDesk',
            'registration_enabled' => false,
        ])
        ->assertRedirect(route('app.manage.system.settings.show'));

    expect($settings->refresh()->registration_enabled)->toBeFalse();
});

test('关闭自主注册后隐藏注册入口并拒绝注册请求', function () {
    [$settings] = createInstanceWithOwner();
    $settings->fill(['registration_enabled' => false])->save();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/Login')
            ->where('canRegister', false)
        );

    $this->get(route('register'))->assertRedirect(route('login'));

    $this->post(route('register.store'), [
        'name' => 'blocked-user',
        'email' => 'blocked@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertForbidden();

    expect(User::query()->where('email', 'blocked@example.com')->exists())->toBeFalse();
});

test('后续注册用户仍需邮箱验证并进入普通后台入口', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $app = createSystemSettings([
        'owner_id' => $owner->id,
        'registration_enabled' => true,
    ]);
    Membership::query()->create(['user_id' => $owner->id]);

    $response = $this->post(route('register.store'), [
        'name' => 'seconduser',
        'email' => 'second@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'second@example.com')->firstOrFail();

    expect($app->refresh()->owner_id)->toBe($owner->id)
        ->and($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo(
        $user,
        QueuedVerifyEmail::class,
        fn (QueuedVerifyEmail $notification) => $notification instanceof ShouldQueue,
    );
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('注册按提交或请求语言存储偏好', function () {
    // 显式提交 locale/timezone → 按提交值存储
    User::factory()->create();
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->post(route('register.store'), [
        'name' => 'localizeduser',
        'email' => 'localized@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'locale' => 'en',
        'timezone' => 'America/New_York',
    ])->assertRedirect(route('app.owner.two-factor.setup', absolute: false));

    $localized = User::query()->where('email', 'localized@example.com')->firstOrFail();
    expect($localized->locale)->toBe('en')
        ->and($localized->timezone)->toBe('America/New_York')
        ->and($localized->preferredLocale())->toBe('en');

    // 注册即登录，换账号前先登出
    $this->post(route('logout'));
    systemSettings()->fill(['registration_enabled' => true])->save();

    // 未提交 locale → 回退到请求语言
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->post(route('register.store'), [
        'name' => 'browseruser',
        'email' => 'browser@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $browser = User::query()->where('email', 'browser@example.com')->firstOrFail();
    expect($browser->locale)->toBe('en');
});

test('中文姓名和姓名内空格可以注册并去除首尾空白', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => '  张 三  ',
        'email' => 'zhangsan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'locale' => 'zh-CN',
    ])->assertRedirect(route('app.owner.two-factor.setup', absolute: false));

    $user = User::query()->where('email', 'zhangsan@example.com')->firstOrFail();

    expect($user->name)->toBe('张 三');
});

test('纯空白姓名不能注册', function () {
    $this->post(route('register.store'), [
        'name' => '   ',
        'email' => 'blank-name@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('name');

    expect(User::query()->where('email', 'blank-name@example.com')->exists())->toBeFalse();
});

test('不同用户可以使用相同姓名', function () {
    createInstanceWithOwner([], ['registration_enabled' => true]);
    $action = app(CreateNewUser::class);

    $first = $action->create([
        'name' => '同名客服',
        'email' => 'same-name-first@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'locale' => 'zh-CN',
    ]);
    $second = $action->create([
        'name' => '同名客服',
        'email' => 'same-name-second@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'locale' => 'zh-CN',
    ]);

    expect($first->name)->toBe('同名客服')
        ->and($second->name)->toBe('同名客服')
        ->and(User::query()->where('name', '同名客服')->count())->toBe(2);
});
