<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

test('密码可以更新', function () {
    $response = $this
        ->actingAs($this->user)
        ->from(route('settings.password.edit', []))
        ->put(route('settings.password.update', []), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.password.edit', []))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => __('common.操作成功'),
        ]);

    expect(Hash::check('new-password', $this->user->refresh()->password))->toBeTrue();
});

test('正确密码必须提供到更新密码', function () {
    $response = $this
        ->actingAs($this->user)
        ->from(route('settings.password.edit', []))
        ->put(route('settings.password.update', []), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('settings.password.edit', []));
});
