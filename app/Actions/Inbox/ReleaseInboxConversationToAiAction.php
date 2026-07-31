<?php

namespace App\Actions\Inbox;

use App\Actions\Reception\ReleaseConversationToAiAction;
use App\Data\CurrentUserContextData;
use App\Enums\ConversationInboxStatus;
use App\Enums\InboxView;
use App\Models\Conversation;
use App\Models\ConversationThread;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 从收件箱把会话交给 AI；AI 不可用时进入待接待队列。
 */
class ReleaseInboxConversationToAiAction
{
    use AsAction;

    /**
     * 注入接待侧释放会话动作。
     */
    public function __construct(
        private readonly ReleaseConversationToAiAction $releaseConversationToAiAction,
    ) {}

    /**
     * 释放线程当前代表会话并返回最新线程投影。
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

        $this->releaseConversationToAiAction->handle($conversation, $user);

        return ConversationThread::requireForConversation($conversation);
    }

    /**
     * 从收件箱入口释放当前会话给 AI 或待接待队列。
     */
    public function asController(Request $request, string $conversationId): RedirectResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $thread = $this->handle(
            user: User::query()->findOrFail($ctx->user_id),
            conversationId: $conversationId,
        );

        return redirect()->route('app.inbox.show', [
            'view' => $thread->inbox_status === ConversationInboxStatus::TeammatePending
                ? InboxView::Pending
                : InboxView::Ai,
            'thread_id' => $thread->id,
        ]);
    }
}
