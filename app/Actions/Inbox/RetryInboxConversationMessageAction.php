<?php

namespace App\Actions\Inbox;

use App\Data\CurrentUserContextData;
use App\Enums\ChannelType;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageOutboxStatus;
use App\Exceptions\BusinessException;
use App\Jobs\Telegram\SendTelegramMessageJob;
use App\Jobs\WechatOfficialAccount\SendWechatOfficialAccountMessageJob;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationThread;
use App\Models\MessageOutbox;
use App\Models\User;
use App\Services\Conversation\ConversationReplyRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** 从收件箱重新投递一条失败的外部渠道消息。 */
class RetryInboxConversationMessageAction
{
    use AsAction;

    /** 注入会话回复规则服务。 */
    public function __construct(private readonly ConversationReplyRule $replyRule) {}

    /** 恢复失败投递，或确认该消息已有进行中的投递任务。 */
    public function handle(User $user, string $conversationId, string $messageId): ConversationThread
    {
        [$conversation, $message, $outbox, $retried] = DB::transaction(function () use ($user, $conversationId, $messageId): array {
            $conversation = Conversation::query()
                ->with('channel')
                ->whereKey($conversationId)
                ->lockForUpdate()
                ->first();

            if (
                $conversation === null
                || ! $this->replyRule->canReply($conversation, $user)
                || ConversationThread::findCurrentForConversation($conversation) === null
            ) {
                throw new NotFoundHttpException;
            }

            if (! in_array($conversation->channel->type, [ChannelType::Telegram, ChannelType::WechatOfficialAccount], true)) {
                throw new BusinessException(__('conversation.errors.message_not_retryable'));
            }

            $message = ConversationMessage::query()
                ->where('conversation_id', $conversation->id)
                ->whereKey($messageId)
                ->lockForUpdate()
                ->first();
            $outbox = $message === null ? null : MessageOutbox::query()
                ->where('conversation_message_id', $message->id)
                ->lockForUpdate()
                ->first();

            if ($message === null || $outbox === null) {
                throw new BusinessException(__('conversation.errors.message_not_retryable'));
            }

            $retried = $outbox->retryFailed();
            if (! $retried && ! in_array($outbox->status, [MessageOutboxStatus::Pending, MessageOutboxStatus::Sending], true)) {
                throw new BusinessException(__('conversation.errors.message_not_retryable'));
            }

            $message->update(['delivery_status' => MessageDeliveryStatus::Sending]);

            if ($retried) {
                if ($conversation->channel->type === ChannelType::Telegram) {
                    SendTelegramMessageJob::dispatch((string) $message->id)->afterCommit();
                } else {
                    SendWechatOfficialAccountMessageJob::dispatch((string) $message->id)->afterCommit();
                }
            }

            return [$conversation, $message, $outbox, $retried];
        });

        $thread = ConversationThread::requireForConversation($conversation);

        Log::info($retried
            ? '收件箱失败消息已重新派发。'
            : '收件箱消息重试请求命中进行中的投递。', [
                'conversation_id' => (string) $conversation->id,
                'message_id' => (string) $message->id,
                'outbox_id' => (string) $outbox->id,
                'outbox_status' => $outbox->status->value,
                'channel_type' => $conversation->channel->type->value,
                'user_id' => (string) $user->id,
            ]);

        return $thread;
    }

    /** 接收消息重试请求并跳回对应收件箱线程。 */
    public function asController(Request $request, string $conversationId, string $messageId): RedirectResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);
        $thread = $this->handle($user, $conversationId, $messageId);

        return redirect()->route('app.inbox.show', [
            'thread_id' => $thread->id,
        ]);
    }
}
