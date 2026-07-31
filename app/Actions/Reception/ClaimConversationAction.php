<?php

namespace App\Actions\Reception;

use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Exceptions\BusinessException;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\User;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 把待处理、AI 接待中或同事接待中的会话分配给当前客服。
 */
class ClaimConversationAction
{
    use AsAction;

    /**
     * 注入实时通知服务。
     */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 将可接管的会话分配给当前客服。
     */
    public function handle(Conversation $conversation, User $actor): Conversation
    {
        if ($conversation->status !== ConversationStatus::Open) {
            throw new BusinessException(__('conversation.errors.already_closed'));
        }

        $assignmentSource = null;

        $claimed = DB::transaction(function () use ($conversation, $actor, &$assignmentSource): ?Conversation {
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedConversation instanceof Conversation) {
                return null;
            }

            if ($lockedConversation->status !== ConversationStatus::Open) {
                throw new BusinessException(__('conversation.errors.already_closed'));
            }

            if (! $this->canClaim($lockedConversation, $actor)) {
                return null;
            }

            $previousAssignedUserId = $lockedConversation->assigned_user_id !== null
                ? (string) $lockedConversation->assigned_user_id
                : null;
            $previousInboxStatus = $lockedConversation->inbox_status;
            $assignmentSource = $this->assignmentSourceFor($lockedConversation, $actor);

            $lockedConversation->update([
                'inbox_status' => ConversationInboxStatus::TeammateHandling,
                'assigned_user_id' => $actor->id,
                'updated_at' => now(),
            ]);

            ConversationEvent::query()->create([
                'conversation_id' => $lockedConversation->id,
                'actor_user_id' => $actor->id,
                'type' => ConversationEventType::AssignmentChanged,
                'payload' => [
                    'source' => $assignmentSource,
                    'previous_user_id' => $previousAssignedUserId,
                    'previous_inbox_status' => $previousInboxStatus->value,
                    'user_id' => (string) $actor->id,
                ],
                'created_at' => now(),
            ]);

            return $lockedConversation->fresh();
        });

        if (! $claimed instanceof Conversation) {
            throw new BusinessException(__('conversation.errors.claim_failed'));
        }

        Log::info('[conversation] 会话已指派客服', [
            'conversation_id' => (string) $claimed->id,
            'actor_user_id' => (string) $actor->id,
            'source' => $assignmentSource,
        ]);

        $this->realtimeNotifier->conversationChanged($claimed, 'conversation_claimed', [
            'assigned_user_id' => (string) $actor->id,
        ]);

        return $claimed;
    }

    /**
     * 判断当前客服是否可以接起或接管这条会话。
     */
    private function canClaim(Conversation $conversation, User $actor): bool
    {
        if ($conversation->inbox_status === ConversationInboxStatus::TeammatePending) {
            return true;
        }

        if (
            $conversation->assigned_user_id === null
            && $conversation->inbox_status === ConversationInboxStatus::AiHandling
        ) {
            return true;
        }

        return $conversation->assigned_user_id !== null
            && (string) $conversation->assigned_user_id !== (string) $actor->id
            && $conversation->inbox_status === ConversationInboxStatus::TeammateHandling;
    }

    /**
     * 生成分配事件来源，方便时间线区分普通接单、AI 转人工和强制接管。
     */
    private function assignmentSourceFor(Conversation $conversation, User $actor): string
    {
        if (
            $conversation->assigned_user_id === null
            && $conversation->inbox_status === ConversationInboxStatus::AiHandling
        ) {
            return 'transfer_to_human';
        }

        if (
            $conversation->assigned_user_id !== null
            && (string) $conversation->assigned_user_id !== (string) $actor->id
        ) {
            return 'takeover';
        }

        return 'claim';
    }
}
