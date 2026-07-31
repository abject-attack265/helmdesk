<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsAfterAuthentication;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * 密码登录成功后的响应：登录用户进入后台收件箱。
 */
class LoginResponse implements LoginResponseContract
{
    use RedirectsAfterAuthentication;

    /**
     * 返回登录后的重定向响应。
     */
    public function toResponse($request): Response
    {
        return $this->redirectAfterAuthentication($request);
    }
}
