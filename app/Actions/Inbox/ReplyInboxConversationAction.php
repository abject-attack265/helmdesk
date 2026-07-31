<?php

namespace App\Actions\Inbox;

use App\Actions\Reception\AppendTeammateMessageAction;
use App\Data\CurrentUserContextData;
use App\Data\Inbox\FormReplyInboxConversationData;
use App\Enums\ConversationInboxStatus;
use App\Enums\InboxView;
use App\Exceptions\BusinessException;
use App\Models\Conversation;
use App\Models\ConversationThread;
use App\Models\User;
use App\Services\LocalePreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 从收件箱发送客服回复。
 */
class ReplyInboxConversationAction
{
    use AsAction;

    /**
     * 注入客服消息追加 Action。
     */
    public function __construct(
        private readonly AppendTeammateMessageAction $appendTeammateMessageAction,
    ) {}

    /**
     * 向线程当前代表会话追加客服回复并返回最新线程投影。
     */
    public function handle(User $user, string $conversationId, FormReplyInboxConversationData $data): ConversationThread
    {
        $conversation = Conversation::query()->find($conversationId);

        if (
            $conversation === null
            || ConversationThread::findCurrentForConversation($conversation) === null
        ) {
            throw new NotFoundHttpException;
        }

        $authorContent = (string) ($data->content ?? '');
        [$visitorContent, $visitorLocale, $sourceLocale] = $this->confirmedVisitorContent($conversation, $data);

        $this->appendTeammateMessageAction->handle(
            conversation: $conversation,
            actor: $user,
            content: $visitorContent ?? $authorContent,
            attachmentIds: $data->attachment_ids,
            clientMsgId: $data->client_msg_id,
            quotedMessageId: $data->quoted_message_id,
            contentLocale: $visitorLocale,
            authorContent: $visitorContent !== null ? $authorContent : null,
            authorLocale: $sourceLocale,
        );

        return ConversationThread::requireForConversation($conversation);
    }

    /**
     * 接收收件箱回复表单并跳回对应线程。
     */
    public function asController(Request $request, string $conversationId): RedirectResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);

        $thread = $this->handle(
            user: $user,
            conversationId: $conversationId,
            data: FormReplyInboxConversationData::from($request),
        );
        $view = $this->resolveViewFor($thread, $user);

        return redirect()->route('app.inbox.show', [
            'view' => $view,
            'thread_id' => $thread->id,
        ]);
    }

    /**
     * 按线程当前归属推断回复后的目标视图。
     */
    private function resolveViewFor(ConversationThread $thread, User $user): InboxView
    {
        if ((string) $thread->assigned_user_id === (string) $user->id) {
            return InboxView::Mine;
        }

        if ($thread->assigned_user_id !== null) {
            return InboxView::Teammates;
        }

        if ($thread->inbox_status === ConversationInboxStatus::AiHandling) {
            return InboxView::Ai;
        }

        return InboxView::Pending;
    }

    /**
     * 只接收当前会话真实会发给访客的已确认内容。
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function confirmedVisitorContent(Conversation $conversation, FormReplyInboxConversationData $data): array
    {
        if ($data->visitor_content === null && $data->visitor_locale === null && $data->source_locale === null) {
            return [null, null, null];
        }

        if ($data->visitor_content === null || $data->visitor_locale === null || $data->source_locale === null) {
            throw new LogicException('收件箱回复翻译字段必须同时提供');
        }

        $text = trim($data->visitor_content);
        $visitorLocale = trim($data->visitor_locale);
        $sourceLocale = trim($data->source_locale);

        if ($text === '' || $visitorLocale === '' || $sourceLocale === '') {
            throw new LogicException('收件箱回复翻译字段不能为空');
        }

        if (! LocalePreference::matches($conversation->visitor_locale, $visitorLocale)) {
            throw new BusinessException(__('conversation.errors.reply_translation_stale'));
        }

        return [$text, $visitorLocale, $sourceLocale];
    }
}
