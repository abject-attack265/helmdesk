<?php

namespace App\Actions\Reception\Visitor;

use App\Actions\Reception\FindExistingWebVisitorConversationAction;
use App\Actions\Reception\SubmitConversationRatingAction;
use App\Data\Reception\FormSubmitConversationRatingData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Services\Reception\ReceptionSession;
use App\Services\Reception\ReceptionStateBuilder;
use App\Services\Reception\VisitorRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 访客提交会话满意度评价入口（POST /api/chat/{code}/rating）。
 *
 * 按网站访客身份定位已关闭会话，提交评价并返回刷新后的接待状态。
 */
class SubmitReceptionRatingAction
{
    use AsAction;

    /**
     * 注入已有网站访客会话查询。
     */
    public function __construct(
        private readonly FindExistingWebVisitorConversationAction $findExistingVisitorConversation,
    ) {}

    /**
     * 解析会话、提交评价并返回刷新后的接待状态。
     */
    public function asController(Request $request, string $code): JsonResponse
    {
        $ctx = VisitorRequestContext::fromRequest($request, $code);
        $sessionToken = $ctx->sessionToken ?? ReceptionSession::generate();

        $channel = Channel::query()
            ->withTrashed()
            ->where('code', $code)
            ->where('type', ChannelType::Web)
            ->first();
        if ($channel === null) {
            throw new NotFoundHttpException;
        }

        $conversation = $this->findExistingVisitorConversation->handle(
            $channel,
            $sessionToken,
            $ctx->userToken,
            acceptExpiredUserToken: true,
        );
        if ($conversation === null) {
            throw new NotFoundHttpException;
        }

        $data = FormSubmitConversationRatingData::from($request);
        SubmitConversationRatingAction::run($conversation, $data->score, $data->comment);

        $conversation->refresh();
        $state = ReceptionStateBuilder::build($channel, $conversation, $sessionToken);
        $response = response()->json($state);

        $cookie = $ctx->sessionCookie($request, $sessionToken);
        if ($cookie !== null) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
