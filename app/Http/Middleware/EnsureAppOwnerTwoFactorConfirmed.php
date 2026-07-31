<?php

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 引导首个应用管理员配置两步验证。
 *
 * 跳过标记只存在于当前会话，重新登录后会再次引导。
 */
class EnsureAppOwnerTwoFactorConfirmed
{
    public const SKIP_SESSION_KEY = 'app_owner_two_factor_setup_skipped';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');
        $settings = app(GeneralSettings::class);

        if ($user === null || (string) $settings->owner_id !== (string) $user->id) {
            return $next($request);
        }

        if ($user->two_factor_confirmed_at !== null
            || $request->session()->get(self::SKIP_SESSION_KEY, false)) {
            return $next($request);
        }

        return redirect()->route('app.owner.two-factor.setup');
    }
}
