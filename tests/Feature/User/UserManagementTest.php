<?php

use App\Enums\UserOnlineStatus;
use App\Enums\UserPermission;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance([], ['name' => 'Test System']);
});

test('管理员可以查看成员列表', function () {
    $member = User::factory()->create(['email' => 'member@example.com']);
    attachMember($member);

    $this->actingAs($this->user)
        ->get(route('app.manage.teammates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teammate/List')
            ->has('user_list', 2)
            ->has('user_list.0', fn (Assert $item) => $item
                ->hasAll([
                    'app_name',
                    'user_id',
                    'user_name',
                    'user_avatar',
                    'user_email',
                    'user_nickname',
                    'user_online_status',
                    'user_last_active_at',
                    'is_owner',
                    'permission_count',
                    'can_edit',
                    'can_delete',
                ])
            )
            ->where('can_create', true)
            ->etc()
        );
});

test('可以按在线状态和名称筛选成员', function () {
    $online = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
    attachMember($online, ['online_status' => UserOnlineStatus::Online->value]);

    $offline = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);
    attachMember($offline, ['online_status' => UserOnlineStatus::Offline->value]);

    $this->actingAs($this->user)
        ->get(route('app.manage.teammates.index', [
            'search' => 'alice',
            'online_status' => UserOnlineStatus::Online->value,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_search', 'alice')
            ->where('current_online_status', (string) UserOnlineStatus::Online->value)
            ->has('user_list', 1)
            ->where('user_list.0.user_email', 'alice@example.com')
            ->etc()
        );
});

test('成员可以打开管理页面', function () {
    $member = User::factory()->create(['email' => 'member@example.com']);
    attachMember($member);

    $this->actingAs($member)
        ->get(route('app.manage.system.settings.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('currentApp/Index'));

    $this->actingAs($member)
        ->get(route('app.manage.teammates.index'))
        ->assertOk();
});

test('成员可以打开新增和邀请页面', function () {
    $member = User::factory()->create([
        'email' => 'member@example.com',
        'permissions' => [UserPermission::UsersCreate->value],
    ]);
    attachMember($member);

    $this->actingAs($member)
        ->get(route('app.manage.teammates.invite'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teammate/Invite')
            ->where('can_assign_permissions', false)
        );

    $this->actingAs($member)
        ->get(route('app.manage.teammates.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teammate/Create')
            ->where('can_assign_permissions', false)
        );
});

test('只有系统管理员可以为新客服分配权限', function () {
    $member = User::factory()->create([
        'permissions' => [UserPermission::UsersCreate->value],
    ]);
    attachMember($member);

    $this->actingAs($member)
        ->post(route('app.manage.teammates.store'), [
            'name' => 'support-agent',
            'email' => 'support-limited@example.com',
            'password' => 'secret-pass-12',
            'password_confirmation' => 'secret-pass-12',
            'permissions' => [UserPermission::SystemSettingsManage->value],
        ])
        ->assertRedirect(route('app.manage.teammates.index'));

    expect(User::query()->where('email', 'support-limited@example.com')->firstOrFail()->permissions)
        ->toBe([]);
});

test('只有系统管理员可以为邀请分配权限', function () {
    $member = User::factory()->create([
        'permissions' => [UserPermission::UsersCreate->value],
    ]);
    attachMember($member);

    $this->actingAs($member)
        ->post(route('app.manage.teammates.invitations.store'), [
            'email' => 'invite-limited@example.com',
            'permissions' => [UserPermission::SystemSettingsManage->value],
        ])
        ->assertRedirect(route('app.manage.teammates.index'));

    expect(Invitation::query()->where('email', 'invite-limited@example.com')->firstOrFail()->permissions)
        ->toBe([]);
});

test('管理员可以邀请新成员', function () {
    $this->actingAs($this->user)
        ->post(route('app.manage.teammates.invitations.store'), [
            'email' => 'invite@example.com',
            'nickname' => '新成员',
            'permissions' => [UserPermission::ContactsView->value],
        ])
        ->assertRedirect(route('app.manage.teammates.index'));

    expect(Invitation::query()->where('email', 'invite@example.com')->firstOrFail()->permissions)
        ->toBe([UserPermission::ContactsView->value]);
});

test('管理员可以直接创建客服并分配权限', function () {
    $this->actingAs($this->user)
        ->post(route('app.manage.teammates.store'), [
            'name' => 'support-agent',
            'email' => 'support@example.com',
            'password' => 'secret-pass-12',
            'password_confirmation' => 'secret-pass-12',
            'nickname' => '在线客服',
            'permissions' => [UserPermission::ContactsView->value],
        ])
        ->assertRedirect(route('app.manage.teammates.index'));

    $user = User::query()->where('email', 'support@example.com')->firstOrFail();

    expect($user->permissions)->toBe([UserPermission::ContactsView->value])
        ->and($user->membership->nickname)->toBe('在线客服')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('可以查看和更新成员资料', function () {
    $member = User::factory()->create([
        'name' => '客服A',
        'email' => 'member@example.com',
    ]);
    attachMember($member);

    $this->actingAs($this->user)
        ->get(route('app.manage.teammates.edit', ['id' => $member->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teammate/Edit')
            ->where('user_form.id', (string) $member->id)
            ->where('can_update_profile', true)
            ->where('can_update_credentials', true)
            ->where('can_assign_permissions', true)
        );

    $this->actingAs($this->user)
        ->put(route('app.manage.teammates.update', ['id' => $member->id]), [
            'name' => '客服A改名',
            'email' => 'member-new@example.com',
            'nickname' => '新昵称',
            'locale' => 'en',
        ])
        ->assertRedirect(route('app.manage.teammates.index'));

    $member->refresh();
    expect($member->name)->toBe('客服A改名');
    expect($member->email)->toBe('member-new@example.com');
    expect($member->locale)->toBe('en');
    expect($member->membership->nickname)->toBe('新昵称');
});

test('更新成员密码后可以使用新密码', function () {
    $member = User::factory()->create([
        'email' => 'member@example.com',
        'password' => Hash::make('old-password'),
    ]);
    attachMember($member);

    $this->actingAs($this->user)
        ->put(route('app.manage.teammates.update', ['id' => $member->id]), [
            'name' => $member->name,
            'email' => $member->email,
            'locale' => 'en',
            'password' => 'new-secret-12',
            'password_confirmation' => 'new-secret-12',
        ])
        ->assertRedirect(route('app.manage.teammates.index'));

    expect(Hash::check('new-secret-12', $member->fresh()->password))->toBeTrue();
});

test('成员可以更新其他成员的资料', function () {
    $editor = User::factory()->create([
        'email' => 'editor@example.com',
        'permissions' => [UserPermission::UsersEdit->value],
    ]);
    attachMember($editor);
    $target = User::factory()->create(['email' => 'target@example.com']);
    attachMember($target);

    $this->actingAs($editor)
        ->get(route('app.manage.teammates.edit', ['id' => $target->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_update_profile', true)
            ->where('can_update_credentials', false)
            ->where('can_assign_permissions', false)
        );

    $this->actingAs($editor)
        ->put(route('app.manage.teammates.update', ['id' => $target->id]), [
            'name' => '更新后的成员',
            'email' => 'target@example.com',
            'locale' => 'en',
        ])
        ->assertRedirect(route('app.manage.teammates.index'));

    expect($target->fresh()->name)->toBe('更新后的成员');
});

test('成员编辑权限不能修改其他成员的登录凭据', function () {
    $editor = User::factory()->create([
        'email' => 'editor@example.com',
        'permissions' => [UserPermission::UsersEdit->value],
    ]);
    attachMember($editor);

    $target = User::factory()->create([
        'email' => 'privileged@example.com',
        'password' => Hash::make('original-password'),
        'permissions' => [UserPermission::SystemSettingsManage->value],
    ]);
    attachMember($target);

    $this->actingAs($editor)
        ->put(route('app.manage.teammates.update', ['id' => $target->id]), [
            'name' => $target->name,
            'email' => 'hijacked@example.com',
            'locale' => $target->locale,
        ])
        ->assertSessionHasErrors(['email']);

    $this->actingAs($editor)
        ->put(route('app.manage.teammates.update', ['id' => $target->id]), [
            'name' => $target->name,
            'email' => $target->email,
            'locale' => $target->locale,
            'password' => 'hijacked-password',
            'password_confirmation' => 'hijacked-password',
        ])
        ->assertSessionHasErrors(['password']);

    $target->refresh();
    expect($target->email)->toBe('privileged@example.com')
        ->and(Hash::check('original-password', $target->password))->toBeTrue()
        ->and($target->permissions)->toBe([UserPermission::SystemSettingsManage->value]);
});

test('成员不能更新系统管理员资料', function () {
    $member = User::factory()->create([
        'email' => 'member@example.com',
        'permissions' => [UserPermission::UsersEdit->value],
    ]);
    attachMember($member);

    $this->actingAs($member)
        ->get(route('app.manage.teammates.edit', ['id' => $this->user->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teammate/Edit')
            ->where('can_update_profile', false)
            ->where('can_update_credentials', false)
            ->where('can_assign_permissions', false)
        );

    $this->actingAs($member)
        ->put(route('app.manage.teammates.update', ['id' => $this->user->id]), [
            'name' => '不应更新',
            'email' => $this->user->email,
            'locale' => 'en',
        ])
        ->assertForbidden();
});

test('非系统管理员提交客服权限时返回字段错误', function () {
    $member = User::factory()->create([
        'email' => 'member@example.com',
        'permissions' => [UserPermission::UsersEdit->value],
    ]);
    attachMember($member);

    $this->actingAs($member)
        ->put(route('app.manage.teammates.update', ['id' => $member->id]), [
            'name' => $member->name,
            'email' => $member->email,
            'locale' => 'en',
            'permissions' => [UserPermission::SystemSettingsManage->value],
        ])
        ->assertSessionHasErrors(['permissions']);

    expect($member->fresh()->permissions)->toBe([UserPermission::UsersEdit->value]);
});

test('成员可以更新在线状态', function () {
    $member = User::factory()->create(['email' => 'member@example.com']);
    attachMember($member, ['online_status' => UserOnlineStatus::Offline->value]);

    $this->actingAs($this->user)
        ->put(route('app.manage.teammates.online-status.update', ['id' => $member->id]), [
            'online_status' => UserOnlineStatus::Online->value,
        ])
        ->assertRedirect();

    expect($member->fresh()->membership->online_status)->toBe(UserOnlineStatus::Online->value);
});

test('成员不能修改系统管理员在线状态', function () {
    $member = User::factory()->create([
        'permissions' => [UserPermission::UsersEdit->value],
    ]);
    attachMember($member);
    $this->user->membership()->update(['online_status' => UserOnlineStatus::Online->value]);

    $this->actingAs($member)
        ->put(route('app.manage.teammates.online-status.update', ['id' => $this->user->id]), [
            'online_status' => UserOnlineStatus::Offline->value,
        ])
        ->assertForbidden();

    expect($this->user->fresh()->membership->online_status)->toBe(UserOnlineStatus::Online->value);
});

test('成员只能管理自己创建的邀请', function () {
    $member = User::factory()->create([
        'permissions' => [UserPermission::UsersCreate->value],
    ]);
    attachMember($member);

    $invitation = Invitation::factory()->create([
        'email' => 'owner-invite@example.com',
        'invited_by' => $this->user->id,
    ]);
    $originalToken = $invitation->token;

    $this->actingAs($member)
        ->post(route('app.manage.teammates.invitations.resend', ['invitation' => $invitation->id]))
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('app.manage.teammates.invitations.destroy', ['invitation' => $invitation->id]))
        ->assertForbidden();

    expect($invitation->fresh()->token)->toBe($originalToken);
});

test('成员可以重发和撤销自己创建的邀请', function () {
    Notification::fake();

    $member = User::factory()->create([
        'permissions' => [UserPermission::UsersCreate->value],
    ]);
    attachMember($member);

    $resendInvitation = Invitation::factory()->create([
        'email' => 'member-resend@example.com',
        'invited_by' => $member->id,
    ]);
    $originalToken = $resendInvitation->token;

    $this->actingAs($member)
        ->post(route('app.manage.teammates.invitations.resend', ['invitation' => $resendInvitation->id]))
        ->assertRedirect(route('app.manage.teammates.index'));

    expect($resendInvitation->fresh()->token)->not->toBe($originalToken);

    $revokeInvitation = Invitation::factory()->create([
        'email' => 'member-revoke@example.com',
        'invited_by' => $member->id,
    ]);

    $this->actingAs($member)
        ->delete(route('app.manage.teammates.invitations.destroy', ['invitation' => $revokeInvitation->id]))
        ->assertRedirect(route('app.manage.teammates.index'));

    expect($revokeInvitation->fresh())->toBeNull();
});

test('不能删除当前登录用户或系统管理员', function () {
    $member = User::factory()->create(['email' => 'member@example.com']);
    attachMember($member);

    $this->actingAs($member)
        ->delete(route('app.manage.teammates.destroy', ['id' => $member->id]))
        ->assertSessionHasErrors(['user_id']);

    $this->actingAs($member)
        ->delete(route('app.manage.teammates.destroy', ['id' => $this->user->id]))
        ->assertSessionHasErrors(['user_id']);
});

test('删除成员只解除系统关联', function () {
    $member = User::factory()->create(['email' => 'member@example.com']);
    attachMember($member);

    $this->actingAs($this->user)
        ->delete(route('app.manage.teammates.destroy', ['id' => $member->id]))
        ->assertRedirect();

    expect(User::query()->whereKey($member->id)->exists())->toBeTrue();
    expect(Membership::query()->whereKey($member->id)->exists())->toBeFalse();
});

test('客服权限限制后台功能', function () {
    $member = User::factory()->create([
        'permissions' => [UserPermission::ContactsView->value],
    ]);
    attachMember($member);

    $this->actingAs($member)
        ->get(route('app.contacts.index', ['type' => 'all']))
        ->assertOk();

    $this->actingAs($member)
        ->get(route('app.manage.teammates.index'))
        ->assertForbidden();
});
