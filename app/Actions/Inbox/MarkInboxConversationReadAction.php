<?php

namespace App\Actions\Inbox;

use App\Data\CurrentUserContextData;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 记录当前负责人在收件箱中已读某条会话。
 */
class MarkInboxConversationReadAction
{
    use AsAction;

    /**
     * 当前用户是会话负责人时，将访客消息已读位置推进到当前时刻。
     */
    public function handle(string $userId, string $conversationId): void
    {
        $conversation = Conversation::query()

            ->find($conversationId);

        if ($conversation === null) {
            throw new NotFoundHttpException;
        }

        if ((string) $conversation->assigned_user_id !== $userId) {
            return;
        }

        $conversation->update([
            'unread_visitor_message_count' => 0,
        ]);
    }

    /**
     * 接收前端打开会话后的已读标记请求。
     */
    public function asController(Request $request, string $conversationId): Response
    {
        $ctx = CurrentUserContextData::fromRequest($request);

        $this->handle(
            userId: $ctx->user_id,
            conversationId: $conversationId,
        );

        return response()->noContent();
    }
}
