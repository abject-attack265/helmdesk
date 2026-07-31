<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * 邮箱验证成功后进入后台。
 */
class VerifyEmailResponse implements VerifyEmailResponseContract
{
    /**
     * 生成邮箱验证完成后的 Fortify 响应。
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        /** @var Request $request */
        return $request->wantsJson()
            ? response()->json('', 204)
            : redirect()->intended(Fortify::redirects('email-verification').'?verified=1');
    }
}
