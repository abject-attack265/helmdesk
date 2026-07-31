<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * 隐藏密码重置请求是否命中注册账号，统一返回已受理响应。
 */
class HiddenPasswordResetLinkRequestResponse implements FailedPasswordResetLinkRequestResponse
{
    /**
     * 返回不暴露账号是否存在的密码重置受理响应。
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $message = trans(Password::RESET_LINK_SENT);

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message])
            : back()->with('status', $message);
    }
}
