<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

test('双因素设置页面需要密码确认当已启用', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = $this->createUserWithInstance();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('settings.two-factor.show', []));

    $response->assertRedirect(route('password.confirm'));
});

test('双因素设置页面不需要密码确认当已禁用', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = $this->createUserWithInstance();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => false,
    ]);

    $this->actingAs($user)
        ->get(route('settings.two-factor.show', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/TwoFactor')
        );
});

test('双因素设置页面返回禁止响应当双因素认证禁用时', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    config(['fortify.features' => []]);

    $user = $this->createUserWithInstance();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('settings.two-factor.show', []))
        ->assertForbidden();
});
