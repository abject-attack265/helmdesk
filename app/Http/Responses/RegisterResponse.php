<?php

namespace App\Http\Responses;

use App\Settings\GeneralSettings;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * 注册成功响应：首个用户先完成管理员安全设置，后续用户进入后台。
 */
class RegisterResponse implements RegisterResponseContract
{
    /**
     * 首个注册用户进入两步验证配置页，其余用户进入默认仪表板。
     */
    public function toResponse($request): Response
    {
        // 注册可能复用已有浏览器会话，清掉旧用户留下的 Sanctum 密码基线。
        if ($request->hasSession()) {
            $request->session()->forget('password_hash_web');
        }

        if ($request->user() !== null
            && (string) app(GeneralSettings::class)->owner_id === (string) $request->user()->id
            && $request->user()->two_factor_confirmed_at === null) {
            return redirect()->to(route('app.owner.two-factor.setup', absolute: false));
        }

        return redirect()->to(route('dashboard', absolute: false));
    }
}
