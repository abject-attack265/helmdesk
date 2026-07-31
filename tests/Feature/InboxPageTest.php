<?php

use App\Models\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

test('访客用户会被重定向到登录页面', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('inbox'))->assertRedirect(route('login'));
});

test('没有成员关系的用户访问应用入口会获得404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('app.dashboard'))
        ->assertNotFound();
});

test('已认证用户进入应用仪表盘和可以打开收件箱', function () {
    [$app, $user] = createInstanceWithOwner();

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertRedirect(route('app.dashboard', []));

    $this->get(route('app.home', []))
        ->assertRedirect(route('app.dashboard', []));

    $this->get(route('app.dashboard', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('app')
            ->has('currentUserContext')
            ->where('currentUserContext.app_name', $app->name)
        );

    $this->get(route('inbox'))
        ->assertRedirect(route('app.inbox.show', []));

    $this->get(route('app.inbox.show', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inbox')
            ->has('enabled_web_channels', 0)
            ->has('reply_assistant_mode_options', 2)
            ->has('reply_polish_tone_options', 4)
            ->has('currentUserContext')
            ->where('currentUserContext.app_name', $app->name)
        );
});

test('访问应用仪表盘刷新成员最后活跃时间戳', function () {
    [$app, $user] = createInstanceWithOwner();
    $previousLastActiveAt = now()->subDay();

    $user->membership()->update([
        'last_active_at' => $previousLastActiveAt,
    ]);

    $this->actingAs($user)
        ->get(route('app.dashboard', []))
        ->assertOk();

    $updatedLastActiveAt = DB::table('memberships')

        ->where('user_id', $user->id)
        ->value('last_active_at');

    expect($updatedLastActiveAt)->not->toBeNull()
        ->and(Carbon::parse((string) $updatedLastActiveAt)->isAfter($previousLastActiveAt))->toBeTrue();
});

test('系统成员可以打开收件箱', function () {
    [$app] = createInstanceWithOwner();

    $member = User::factory()->create();
    attachMember($member);

    $this->actingAs($member)
        ->get(route('app.inbox.show', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inbox')
            ->where('currentUserContext.is_owner', false)
        );
});

test('收件箱只显示非已删除网页频道', function () {
    [$app, $user] = createInstanceWithOwner();

    $enabledChannel = Channel::factory()->create([
        'name' => '官网主站',
    ]);

    $secondChannel = Channel::factory()->create([
        'name' => '帮助中心',
    ]);

    $deletedChannel = Channel::factory()->create([
        'name' => '已删除网站',
    ]);
    $deletedChannel->delete();

    $this->actingAs($user)
        ->get(route('app.inbox.show', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inbox')
            ->has('enabled_web_channels', 2)
            ->where('enabled_web_channels.0.id', (string) $enabledChannel->id)
            ->where('enabled_web_channels.0.name', '官网主站')
            ->where('enabled_web_channels.1.id', (string) $secondChannel->id)
            ->where('enabled_web_channels.0.type_label', '网站')
        );
});
