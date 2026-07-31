<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 模拟勾选记住我后会话丢失，仅凭 web recaller cookie 回访仍保持登录。
 */
function reauthenticateViaRememberCookie(User $user, string $visitUrl): void
{
    $login = test()->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => 'on',
    ]);

    $recaller = collect($login->headers->getCookies())
        ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

    expect($recaller)->not->toBeNull('登录应签发 remember_web_ cookie');

    test()->flushSession();
    app('auth')->forgetGuards();

    test()->withUnencryptedCookie($recaller->getName(), $recaller->getValue())->get($visitUrl);
}

test('用户勾选记住我后会话丢失仍可凭 cookie 续登', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    reauthenticateViaRememberCookie($user, route('inbox', absolute: false));

    $this->assertAuthenticated('web');
});
