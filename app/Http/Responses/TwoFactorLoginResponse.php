<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsAfterAuthentication;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * 两步验证挑战通过后的登录收尾响应。
 */
class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    use RedirectsAfterAuthentication;

    /**
     * 返回两步验证通过后的重定向响应。
     */
    public function toResponse($request): Response
    {
        return $this->redirectAfterAuthentication($request);
    }
}
