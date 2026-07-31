<?php

namespace App\Actions\Reception\Visitor;

use App\Actions\Reception\LoadExistingReceptionStateAction;
use App\Services\Reception\VisitorRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 返回网站访客已有会话状态；读取请求不创建业务资源。
 */
class ShowReceptionStateAction
{
    use AsAction;

    /**
     * 注入已有接待状态查询。
     */
    public function __construct(
        private readonly LoadExistingReceptionStateAction $loadExistingReceptionState,
    ) {}

    /**
     * 返回当前会话快照或空状态并同步会话 cookie。
     */
    public function asController(Request $request, string $code): JsonResponse
    {
        $ctx = VisitorRequestContext::fromRequest($request, $code);

        $state = $this->loadExistingReceptionState->handle(
            channelCode: $code,
            sessionToken: $ctx->sessionToken,
            userToken: $ctx->userToken,
        );

        $response = response()->json($state);

        $cookie = $ctx->sessionCookie($request, $state->session_token);
        if ($cookie !== null) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
