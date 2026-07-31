<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 统一处理应用后台 web session guard 的登出与会话清理。
 *
 * Auth::guard()->logout() 只清登录态，不清这个基线哈希；若残留，下次 auth:sanctum 请求会拿它
 * 与当前身份的密码哈希比对，不一致即 flush 整个会话。这里把基线哈希的清理收口到一处。
 */
class SessionGuardManager
{
    /**
     * 登出指定 session guard：清除登录态与其 Sanctum 基线密码哈希，并轮换会话 id 与 CSRF token。
     * 仅作用于当前 web guard。
     */
    public function logout(Request $request, string $guard): void
    {
        Auth::guard($guard)->logout();

        $this->forgetPasswordHash($guard);

        if ($request->hasSession()) {
            $request->session()->regenerate();
            $request->session()->regenerateToken();
        }
    }

    /**
     * 清除某个 guard 的 Sanctum 基线密码哈希 password_hash_<guard>。
     * 用于切换身份后重置基线，让 Sanctum 下次请求按新身份重新写入。
     */
    public function forgetPasswordHash(string $guard): void
    {
        session()->forget("password_hash_{$guard}");
    }
}
