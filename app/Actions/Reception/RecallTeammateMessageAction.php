<?php

namespace App\Actions\Reception;

use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Exceptions\BusinessException;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 客服撤回会话内自己发送或 AI 发出的消息。
 *
 * 撤回保留原 content，并同步搜索索引和会话消息预览；工具类消息不允许撤回。
 */
class RecallTeammateMessageAction
{
    use AsAction;

    /**
     * 注入实时通知器。
     */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 撤回指定消息。当前用户必须是会话负责人，且消息属于自己或当前由 AI 发出。
     */
    public function handle(
        Conversation $conversation,
        User $actor,
        string $messageId,
    ): ConversationMessage {
        [$conversation, $message] = DB::transaction(function () use ($conversation, $actor, $messageId): array {
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedConversation->status !== ConversationStatus::Open) {
                throw new BusinessException(__('conversation.errors.already_closed'));
            }

            if ((string) $lockedConversation->assigned_user_id !== (string) $actor->id) {
                throw new BusinessException(__('conversation.errors.recall_not_assignee'));
            }

            $message = ConversationMessage::query()
                ->where('conversation_id', $lockedConversation->id)
                ->whereKey($messageId)
                ->lockForUpdate()
                ->first();

            if ($message === null) {
                throw new NotFoundHttpException(__('conversation.errors.message_not_found'));
            }

            $isOwnTeammateMessage = $message->role === MessageRole::Teammate
                && (string) $message->sender_user_id === (string) $actor->id;
            $isAiMessage = $message->role === MessageRole::Ai;

            if (! $isOwnTeammateMessage && ! $isAiMessage) {
                throw new BusinessException(__('conversation.errors.recall_not_owner'));
            }

            if (in_array($message->kind, [MessageKind::ToolCall, MessageKind::ToolResult], true)) {
                throw new BusinessException(__('conversation.errors.recall_kind_not_allowed'));
            }

            if ($message->isRecalled()) {
                throw new BusinessException(__('conversation.errors.recall_already_recalled'));
            }

            if (! $message->isWithinRecallWindow()) {
                throw new BusinessException(__('conversation.errors.recall_window_expired', [
                    'minutes' => ConversationMessage::RECALL_WINDOW_MINUTES,
                ]));
            }

            $message->markRecalled($lockedConversation);

            return [$lockedConversation->refresh(), $message->refresh()];
        });

        Log::info('客服消息已撤回', [
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $message->id,
            'message_role' => $message->role->value,
            'actor_user_id' => (string) $actor->id,
        ]);

        $this->realtimeNotifier->conversationChanged(
            $conversation,
            'message_recalled',
            meta: ['message_id' => (string) $message->id],
        );

        return $message;
    }
}
