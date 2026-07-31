<?php

namespace App\Actions\Reception\Visitor;

use App\Actions\Reception\RecallVisitorMessageAction;
use App\Services\Reception\ReceptionPipelineDispatcher;
use App\Services\Reception\VisitorRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 访客撤回消息入口（POST /api/chat/{code}/messages/{messageId}/recall）。
 *
 * 撤回落库后从接待缓冲移除对应消息，避免后续回复继续使用已撤回内容。
 */
class RecallReceptionMessageAction
{
    use AsAction;

    /**
     * 注入撤回业务 Action 与接待管线调度器。
     */
    public function __construct(
        private readonly RecallVisitorMessageAction $recallVisitorMessage,
        private readonly ReceptionPipelineDispatcher $pipeline,
    ) {}

    /**
     * 处理访客撤回请求。
     */
    public function asController(Request $request, string $code, string $messageId): JsonResponse
    {
        $ctx = VisitorRequestContext::fromRequest($request, $code);

        $state = $this->recallVisitorMessage->handle(
            channelCode: $code,
            sessionToken: $ctx->sessionToken,
            messageId: $messageId,
            userToken: $ctx->userToken,
        );

        if ($state->conversation_id !== null) {
            $this->pipeline->notifyMessageRecalled($state->conversation_id, $messageId);
        }

        $response = response()->json($state);

        $cookie = $ctx->sessionCookie($request, $state->session_token);
        if ($cookie !== null) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
