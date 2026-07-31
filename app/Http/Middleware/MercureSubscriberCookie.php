<?php

namespace App\Http\Middleware;

use App\Services\Realtime\MercureSubscriberToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class MercureSubscriberCookie
{
    public function __construct(
        private readonly MercureSubscriberToken $token,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->setCookie(Cookie::create(
            name: 'mercureAuthorization',
            value: $this->token->issue(),
            expire: now()->addHours(12),
            path: '/.well-known/mercure',
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        ));

        return $response;
    }
}
