<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * 设置页访问校验中间件。
 */
class AuthenticateSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('web')) {
            return redirect()->route('login');
        }

        Inertia::share('auth', ['user' => $request->user()]);

        return $next($request);
    }
}
