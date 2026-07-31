<?php

namespace App\Actions\Inbox;

use App\Actions\Reception\ReopenConversationAction;
use App\Data\CurrentUserContextData;
use App\Enums\InboxView;
use App\Models\Conversation;
use App\Models\ConversationThread;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 从收件箱重新打开已关闭会话并分配给当前用户。
 */
class ReopenInboxConversationAction
{
    use AsAction;

    /**
     * 注入接待侧会话重开动作。
     */
    public function __construct(
        private readonly ReopenConversationAction $reopenConversationAction,
    ) {}

    /**
     * 重新打开线程当前代表会话并返回线程。
     */
    public function handle(User $user, string $conversationId): ConversationThread
    {
        $conversation = Conversation::query()->find($conversationId);

        if (
            $conversation === null
            || ConversationThread::findCurrentForConversation($conversation) === null
        ) {
            throw new NotFoundHttpException;
        }

        $this->reopenConversationAction->handle($conversation, $user);

        return ConversationThread::requireForConversation($conversation);
    }

    /**
     * 接收重开请求并切到当前用户视图。
     */
    public function asController(Request $request, string $conversationId): RedirectResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $thread = $this->handle(
            user: User::query()->findOrFail($ctx->user_id),
            conversationId: $conversationId,
        );

        return redirect()->route('app.inbox.show', [
            'view' => InboxView::Mine,
            'thread_id' => $thread->id,
        ]);
    }
}
