<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 认证成功后的统一落点逻辑。
 */
trait RedirectsAfterAuthentication
{
    /**
     * 统一进入后台收件箱。
     */
    protected function redirectAfterAuthentication(Request $request): Response
    {
        // 用户切换后不能继续使用旧用户的 Sanctum 密码基线。
        if ($request->hasSession()) {
            $request->session()->forget('password_hash_web');
        }

        return redirect()->to(route('inbox', absolute: false));
    }
}
