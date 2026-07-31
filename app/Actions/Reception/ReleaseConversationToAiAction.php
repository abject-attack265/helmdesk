<?php

namespace App\Actions\Reception;

use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageRole;
use App\Exceptions\BusinessException;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use App\Services\Reception\ChannelAiAvailability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 将客服接待中的会话释放回 AI 或访客等待状态。
 */
class ReleaseConversationToAiAction
{
    use AsAction;

    /**
     * 注入实时通知和渠道 AI 可用性服务。
     */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
        private readonly ChannelAiAvailability $aiAvailability,
        private readonly DispatchAiCatchUpTurnAction $dispatchAiCatchUpTurnAction,
    ) {}

    /**
     * 校验当前客服状态并释放会话给 AI 或待接待队列。
     *
     * 排队中（无人负责）的会话任何坐席都可直接交给 AI；人工接待中的会话仅限当前负责人释放。
     */
    public function handle(Conversation $conversation, User $actor): Conversation
    {
        $conversation = DB::transaction(function () use ($conversation, $actor): Conversation {
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedConversation->status !== ConversationStatus::Open) {
                throw new BusinessException(__('conversation.errors.already_closed'));
            }

            if ($lockedConversation->inbox_status === ConversationInboxStatus::AiHandling) {
                throw new BusinessException(__('conversation.errors.already_ai_handling'));
            }

            $isPendingQueue = $lockedConversation->inbox_status === ConversationInboxStatus::TeammatePending;

            if (! $isPendingQueue && (string) $lockedConversation->assigned_user_id !== (string) $actor->id) {
                throw new BusinessException(__('conversation.errors.release_to_ai_not_allowed'));
            }

            $canUseAi = $this->conversationCanUseAi($lockedConversation);

            if ($isPendingQueue && ! $canUseAi) {
                throw new BusinessException(__('conversation.errors.release_to_ai_unavailable'));
            }

            $nextInboxStatus = $canUseAi
                ? ConversationInboxStatus::AiHandling
                : ConversationInboxStatus::TeammatePending;
            $waitingForVisitorReply = $canUseAi && ! $this->lastMessageIsFromVisitor($lockedConversation);
            $previousAssignedUserId = $lockedConversation->assigned_user_id !== null
                ? (string) $lockedConversation->assigned_user_id
                : null;

            $lockedConversation->update([
                'assigned_user_id' => null,
                'inbox_status' => $nextInboxStatus,
                'waiting_for_visitor_reply' => $waitingForVisitorReply,
            ]);

            ConversationEvent::query()->create([
                'conversation_id' => $lockedConversation->id,
                'actor_user_id' => $actor->id,
                'type' => ConversationEventType::AssignmentChanged,
                'payload' => [
                    'source' => 'release_to_ai',
                    'previous_user_id' => $previousAssignedUserId,
                    'inbox_status' => $nextInboxStatus->value,
                    'waiting_for_visitor_reply' => $waitingForVisitorReply,
                ],
                'created_at' => now(),
            ]);

            return $lockedConversation->fresh();
        });

        Log::info('[conversation] 会话接待责任已释放', [
            'conversation_id' => (string) $conversation->id,
            'actor_user_id' => (string) $actor->id,
            'inbox_status' => $conversation->inbox_status->value,
        ]);

        if ($conversation->inbox_status === ConversationInboxStatus::AiHandling) {
            // 补答排队/人工期间积压的访客消息。
            $this->dispatchAiCatchUpTurnAction->handle($conversation);
        }

        $this->realtimeNotifier->conversationChanged($conversation, 'conversation_released_to_ai', [
            'inbox_status' => $conversation->inbox_status->value,
        ]);

        return $conversation;
    }

    /**
     * 判断会话所属渠道是否仍可交给 AI 接待。
     */
    private function conversationCanUseAi(Conversation $conversation): bool
    {
        $conversation->loadMissing('channel');

        return $conversation->channel !== null
            && $this->aiAvailability->canUseAi($conversation->channel);
    }

    /**
     * 判断会话最后一条消息是否来自访客。
     */
    private function lastMessageIsFromVisitor(Conversation $conversation): bool
    {
        $lastMessage = ConversationMessage::query()

            ->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $lastMessage?->role === MessageRole::Visitor;
    }
}
