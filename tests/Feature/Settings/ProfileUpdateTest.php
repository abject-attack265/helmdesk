<?php

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

test('配置档信息可以更新', function () {
    $response = $this
        ->actingAs($this->user)
        ->patch(route('settings.profile.update', []), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.profile.edit', []));

    $this->user->refresh();

    expect($this->user->name)->toBe('Test User');
    expect($this->user->email)->toBe('test@example.com');
    expect($this->user->email_verified_at)->toBeNull();
});

test('邮箱验证状态保持不变当邮箱地址保持不变', function () {
    $response = $this
        ->actingAs($this->user)
        ->patch(route('settings.profile.update', []), [
            'name' => 'Test User',
            'email' => $this->user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.profile.edit', []));

    expect($this->user->refresh()->email_verified_at)->not->toBeNull();
});

test('非所有者用户可以删除其账号', function () {
    $member = User::factory()->create();
    Membership::query()->create(['user_id' => $member->id]);

    // DELETE 端点仅能由 Inertia/XHR 触发；home 是纯 Blade 页，
    // 须返回 409 + X-Inertia-Location 整页跳转，否则首页会被塞进 modal iframe。
    $response = $this
        ->actingAs($member)
        ->withHeaders(['X-Inertia' => 'true'])
        ->delete(route('settings.profile.destroy', []), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('home', absolute: false));

    $this->assertGuest();
    expect($member->fresh())->toBeNull();
});

test('应用所有者不能注销自己的账号', function () {
    $response = $this
        ->actingAs($this->user)
        ->delete(route('settings.profile.destroy', []), [
            'password' => 'password',
        ]);

    $response->assertStatus(422);

    $this->assertAuthenticated();
    expect($this->user->fresh())->not->toBeNull();
});

test('正确密码必须提供到删除账号', function () {
    $response = $this
        ->actingAs($this->user)
        ->from(route('settings.profile.edit', []))
        ->delete(route('settings.profile.destroy', []), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('settings.profile.edit', []));

    expect($this->user->fresh())->not->toBeNull();
});
