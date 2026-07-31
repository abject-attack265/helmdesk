<?php

namespace App\Actions\Inbox;

use App\Actions\Reception\RecallTeammateMessageAction;
use App\Data\CurrentUserContextData;
use App\Models\Conversation;
use App\Models\ConversationThread;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** 当前负责人从收件箱撤回自己发送或 AI 发出的消息。 */
class RecallInboxConversationMessageAction
{
    use AsAction;

    /**
     * 注入客服侧撤回 Action。
     */
    public function __construct(
        private readonly RecallTeammateMessageAction $recallTeammateMessageAction,
    ) {}

    /**
     * 校验会话归属、撤回消息并返回所属线程。
     */
    public function handle(User $user, string $conversationId, string $messageId): ConversationThread
    {
        $conversation = Conversation::query()->find($conversationId);

        if (
            $conversation === null
            || ConversationThread::findCurrentForConversation($conversation) === null
        ) {
            throw new NotFoundHttpException;
        }

        $this->recallTeammateMessageAction->handle(
            conversation: $conversation,
            actor: $user,
            messageId: $messageId,
        );

        return ConversationThread::requireForConversation($conversation);
    }

    /**
     * 接收撤回请求并回到收件箱页面。
     */
    public function asController(Request $request, string $conversationId, string $messageId): RedirectResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);

        $thread = $this->handle(
            user: $user,
            conversationId: $conversationId,
            messageId: $messageId,
        );

        return redirect()->route('app.inbox.show', [
            'thread_id' => $thread->id,
        ]);
    }
}
