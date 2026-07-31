<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('应用后台登出 web 并清除 Sanctum 基线哈希 password_hash_web', function () {
    $user = User::factory()->create();

    // 模拟上传后 Sanctum 写入的旧身份基线哈希；若登出不清除，换人登录后首个 auth:sanctum
    // 请求会拿它比对新身份不符而 flush 整个会话、把刚登录的用户也登出。
    actingAs($user, 'web')
        ->withSession(['password_hash_web' => 'stale-hash'])
        ->post(route('logout.web'))
        ->assertRedirect(route('home', absolute: false));

    expect(auth('web')->check())->toBeFalse();
    expect(session('password_hash_web'))->toBeNull();
});
