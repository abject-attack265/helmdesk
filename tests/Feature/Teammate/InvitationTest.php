<?php

use App\Enums\UserOnlineStatus;
use App\Enums\UserPermission;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance([], ['name' => 'Invite Instance']);
});

test('邀请客服会生成邀请记录并发送邀请邮件', function () {
    Notification::fake();

    $this->actingAs($this->user)
        ->post(route('app.manage.teammates.invitations.store'), [
            'email' => 'invitee@example.com',
            'nickname' => '小新',
            'permissions' => [UserPermission::ContactsView->value],
        ])
        ->assertRedirect(route('app.manage.teammates.index'));

    $invitation = Invitation::query()->where('email', 'invitee@example.com')->firstOrFail();
    expect($invitation->nickname)->toBe('小新');
    expect($invitation->permissions)->toBe([UserPermission::ContactsView->value]);
    expect($invitation->accepted_at)->toBeNull();
    expect($invitation->expires_at->isFuture())->toBeTrue();
    expect($invitation->token)->toHaveLength(64);

    Notification::assertSentOnDemand(InvitationNotification::class);
});

test('重复邀请同一邮箱会更新原记录并重新生成令牌', function () {
    Notification::fake();

    $this->actingAs($this->user)
        ->post(route('app.manage.teammates.invitations.store'), ['email' => 'again@example.com'])
        ->assertRedirect();
    $first = Invitation::query()->where('email', 'again@example.com')->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('app.manage.teammates.invitations.store'), [
            'email' => 'again@example.com',
            'nickname' => '改名',
        ])
        ->assertRedirect();

    $second = Invitation::query()->where('email', 'again@example.com')->firstOrFail();
    expect(Invitation::query()->where('email', 'again@example.com')->count())->toBe(1);
    expect($second->id)->toBe($first->id);
    expect($second->nickname)->toBe('改名');
    expect($second->token)->not->toBe($first->token);
});

test('邀请已注册邮箱时校验失败', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $this->actingAs($this->user)
        ->post(route('app.manage.teammates.invitations.store'), ['email' => 'existing@example.com'])
        ->assertSessionHasErrors(['email']);

    expect(Invitation::query()->where('email', 'existing@example.com')->exists())->toBeFalse();
});

test('接受有效邀请会建账号并登录', function () {
    $this->instance->fill(['registration_enabled' => false])->save();

    $invitation = Invitation::factory()
        ->withPlainToken('plaintokenabc')
        ->create([
            'email' => 'accept@example.com',
            'nickname' => '被邀请',
            'permissions' => [UserPermission::KnowledgeBasesView->value],
            'invited_by' => $this->user->id,
        ]);

    $this->post(route('invitations.accept.store', ['token' => 'plaintokenabc']), [
        'name' => '新成员',
        'password' => 'secret-pass-12',
        'password_confirmation' => 'secret-pass-12',
    ])->assertRedirect(route('app.dashboard'));

    $created = User::query()->where('email', 'accept@example.com')->firstOrFail();
    expect($created->email_verified_at)->not->toBeNull();
    expect($created->permissions)->toBe([UserPermission::KnowledgeBasesView->value]);
    expect(Hash::check('secret-pass-12', $created->password))->toBeTrue();

    $membership = Membership::query()->whereKey($created->id)->firstOrFail();
    expect($membership->nickname)->toBe('被邀请');
    expect((int) $membership->online_status)->toBe(UserOnlineStatus::Online->value);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
    $this->assertAuthenticatedAs($created);
});

test('已接受的邀请令牌不能再次使用', function () {
    Invitation::factory()->withPlainToken('usedtoken')->accepted()->create([
        'email' => 'used@example.com',
        'invited_by' => $this->user->id,
    ]);

    $this->get(route('invitations.accept.show', ['token' => 'usedtoken']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AcceptInvitation')
            ->where('invalid', true)
        );

    $this->post(route('invitations.accept.store', ['token' => 'usedtoken']), [
        'name' => 'x',
        'password' => 'secret-pass-12',
        'password_confirmation' => 'secret-pass-12',
    ])->assertStatus(422);

    expect(User::query()->where('email', 'used@example.com')->exists())->toBeFalse();
});

test('过期邀请令牌不能接受', function () {
    Invitation::factory()->withPlainToken('expiredtoken')->expired()->create([
        'email' => 'expired@example.com',
        'invited_by' => $this->user->id,
    ]);

    $this->post(route('invitations.accept.store', ['token' => 'expiredtoken']), [
        'name' => 'x',
        'password' => 'secret-pass-12',
        'password_confirmation' => 'secret-pass-12',
    ])->assertStatus(422);

    expect(User::query()->where('email', 'expired@example.com')->exists())->toBeFalse();
});

test('接受邀请时邮箱已被注册会被拦截', function () {
    Invitation::factory()->withPlainToken('takentoken')->create([
        'email' => 'taken@example.com',
        'invited_by' => $this->user->id,
        'expires_at' => now()->addDay(),
    ]);
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('invitations.accept.store', ['token' => 'takentoken']), [
        'name' => '重复',
        'password' => 'secret-pass-12',
        'password_confirmation' => 'secret-pass-12',
    ])->assertStatus(422);

    expect(User::query()->whereHas('membership')->where('email', 'taken@example.com')->exists())->toBeFalse();
});

test('邀请人被删除后接受页仍可正常打开', function () {
    $inviter = User::factory()->create(['email' => 'gone@example.com']);
    Membership::query()->create(['user_id' => $inviter->id]);

    Invitation::factory()->withPlainToken('livetoken')->create([
        'email' => 'live@example.com',
        'invited_by' => $inviter->id,
        'expires_at' => now()->addDay(),
    ]);
    $inviter->delete();

    $this->get(route('invitations.accept.show', ['token' => 'livetoken']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/AcceptInvitation')
            ->has('invitation')
        );
});

test('重发邀请会刷新令牌并再次发送邮件', function () {
    Notification::fake();
    $invitation = Invitation::factory()->create([
        'email' => 'resend@example.com',
        'invited_by' => $this->user->id,
        'expires_at' => now()->addDay(),
    ]);
    $oldToken = $invitation->token;

    $this->actingAs($this->user)
        ->post(route('app.manage.teammates.invitations.resend', ['invitation' => $invitation->id]))
        ->assertRedirect(route('app.manage.teammates.index'));

    $fresh = $invitation->fresh();
    expect($fresh->token)->not->toBe($oldToken);
    expect($fresh->expires_at->isFuture())->toBeTrue();
    Notification::assertSentOnDemand(InvitationNotification::class);
});

test('撤销邀请会删除待接受记录', function () {
    $invitation = Invitation::factory()->create([
        'email' => 'revoke@example.com',
        'invited_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->delete(route('app.manage.teammates.invitations.destroy', ['invitation' => $invitation->id]))
        ->assertRedirect(route('app.manage.teammates.index'));

    expect(Invitation::query()->whereKey($invitation->id)->exists())->toBeFalse();
});
