<?php

namespace App\Actions\Inbox;

use App\Actions\Reception\TransferConversationToTeammateAction;
use App\Data\CurrentUserContextData;
use App\Data\Inbox\FormTransferInboxConversationData;
use App\Enums\InboxView;
use App\Models\Conversation;
use App\Models\ConversationThread;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 从收件箱将当前客服负责的会话转接给指定同事。
 */
class TransferInboxConversationAction
{
    use AsAction;

    /**
     * 注入接待侧会话转接动作。
     */
    public function __construct(
        private readonly TransferConversationToTeammateAction $transferConversationToTeammateAction,
    ) {}

    /**
     * 转接线程当前代表会话并返回线程。
     */
    public function handle(
        User $user,
        string $conversationId,
        FormTransferInboxConversationData $data,
    ): ConversationThread {
        $conversation = Conversation::query()->find($conversationId);

        if (
            $conversation === null
            || ConversationThread::findCurrentForConversation($conversation) === null
        ) {
            throw new NotFoundHttpException;
        }

        $target = User::query()
            ->whereHas('membership')
            ->whereKey($data->target_user_id)
            ->first();

        if (! $target instanceof User) {
            throw ValidationException::withMessages([
                'target_user_id' => __('conversation.errors.transfer_target_not_found'),
            ]);
        }

        $this->transferConversationToTeammateAction->handle($conversation, $user, $target);

        return ConversationThread::requireForConversation($conversation);
    }

    /**
     * 接收转接请求并切到同事视图。
     */
    public function asController(Request $request, string $conversationId): RedirectResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $data = FormTransferInboxConversationData::from($request);
        $thread = $this->handle(
            user: User::query()->findOrFail($ctx->user_id),
            conversationId: $conversationId,
            data: $data,
        );

        return redirect()->route('app.inbox.show', [
            'view' => InboxView::Teammates,
            'thread_id' => $thread->id,
        ]);
    }
}
