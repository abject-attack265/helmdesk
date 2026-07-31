<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

/**
 * 在启用双因子敏感操作前确认密码。
 */
class ConfirmPasswordWhenTwoFactorRequiresIt
{
    /**
     * 注入框架密码确认中间件。
     */
    public function __construct(
        private RequirePassword $requirePassword,
    ) {}

    /**
     * 根据双因子配置决定是否要求确认密码。
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
            return $this->requirePassword->handle($request, $next);
        }

        return $next($request);
    }
}
