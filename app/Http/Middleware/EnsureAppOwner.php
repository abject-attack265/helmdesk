<?php

namespace App\Http\Middleware;

use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 仅允许单一应用所有者访问初始化管理员安全设置。
 */
class EnsureAppOwner
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');
        $settings = app(GeneralSettings::class);

        abort_unless(
            $user !== null && (string) $settings->owner_id === (string) $user->id,
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
