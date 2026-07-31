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
 * 将当前客服负责的会话转接给另一位同事。
 */
class TransferConversationToTeammateAction
{
    use AsAction;

    /**
     * 注入实时通知服务。
     */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 校验归属并完成会话负责人转移。
     */
    public function handle(Conversation $conversation, User $actor, User $target): Conversation
    {
        if ((string) $actor->id === (string) $target->id) {
            throw new BusinessException(__('conversation.errors.transfer_target_must_be_teammate'));
        }

        if (! $this->targetIsMember($target)) {
            throw new BusinessException(__('conversation.errors.transfer_target_not_found'));
        }

        $conversation = DB::transaction(function () use ($conversation, $actor, $target): Conversation {
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedConversation->status !== ConversationStatus::Open) {
                throw new BusinessException(__('conversation.errors.already_closed'));
            }

            if (
                $lockedConversation->assigned_user_id === null
                || (string) $lockedConversation->assigned_user_id !== (string) $actor->id
                || $lockedConversation->inbox_status !== ConversationInboxStatus::TeammateHandling
            ) {
                throw new BusinessException(__('conversation.errors.transfer_to_teammate_not_allowed'));
            }

            $previousAssignedUserId = (string) $lockedConversation->assigned_user_id;

            $lockedConversation->update([
                'assigned_user_id' => $target->id,
                'inbox_status' => ConversationInboxStatus::TeammateHandling,
            ]);

            ConversationEvent::query()->create([
                'conversation_id' => $lockedConversation->id,
                'actor_user_id' => $actor->id,
                'type' => ConversationEventType::AssignmentChanged,
                'payload' => [
                    'source' => 'transfer_to_teammate',
                    'previous_user_id' => $previousAssignedUserId,
                    'user_id' => (string) $target->id,
                ],
                'created_at' => now(),
            ]);

            return $lockedConversation->fresh();
        });

        Log::info('[conversation] 会话已转接同事', [
            'conversation_id' => (string) $conversation->id,
            'actor_user_id' => (string) $actor->id,
            'target_user_id' => (string) $target->id,
        ]);

        $this->realtimeNotifier->conversationChanged($conversation, 'conversation_transferred', [
            'assigned_user_id' => (string) $target->id,
            'previous_assigned_user_id' => (string) $actor->id,
        ]);

        return $conversation;
    }

    /**
     * 确保转接目标仍是当前会话应用的有效成员。
     */
    private function targetIsMember(User $target): bool
    {
        return $target->membership()->exists();
    }
}
