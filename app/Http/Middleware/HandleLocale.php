<?php

namespace App\Http\Middleware;

use App\Services\LocalePreference;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * 按请求上下文设置后端语言。
 */
class HandleLocale
{
    /**
     * 按请求上下文设置应用语言后继续处理。
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    /**
     * 按账号偏好、语言 Cookie 和浏览器语言依次解析 Laravel 语言标识。
     */
    private function resolveLocale(Request $request): string
    {
        return LocalePreference::normalizeLaravel(
            $request->user('web')?->locale
                ?? $request->cookie('locale')
                ?? LocalePreference::preferredBrowserLocale($request)
        );
    }
}
