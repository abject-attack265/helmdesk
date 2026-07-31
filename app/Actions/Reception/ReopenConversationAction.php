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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 重新打开已关闭会话并分配给操作人。
 */
class ReopenConversationAction
{
    use AsAction;

    /**
     * 注入实时通知服务。
     */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 重新打开已关闭会话，并分配给当前操作人。
     */
    public function handle(Conversation $conversation, User $actor): Conversation
    {
        try {
            $conversation = DB::transaction(function () use ($conversation, $actor): Conversation {
                $lockedConversation = Conversation::query()
                    ->whereKey($conversation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedConversation->status === ConversationStatus::Open) {
                    throw new BusinessException(__('conversation.errors.already_open'));
                }

                if ($this->hasAnotherOpenConversation($lockedConversation)) {
                    throw new BusinessException(__('conversation.errors.reopen_conflicts_with_open_conversation'));
                }

                $lockedConversation->update([
                    'assigned_user_id' => $actor->id,
                    'status' => ConversationStatus::Open,
                    'inbox_status' => ConversationInboxStatus::TeammateHandling,
                    'waiting_for_visitor_reply' => false,
                    'closed_at' => null,
                    'reopened_at' => now(),
                ]);

                ConversationEvent::query()->create([
                    'conversation_id' => $lockedConversation->id,
                    'actor_user_id' => $actor->id,
                    'type' => ConversationEventType::StatusChanged,
                    'payload' => [
                        'status' => ConversationStatus::Open->value,
                        'source' => 'reopen',
                        'user_id' => (string) $actor->id,
                    ],
                    'created_at' => now(),
                ]);

                return $lockedConversation->fresh();
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($this->hasAnotherOpenConversation($conversation)) {
                throw new BusinessException(__('conversation.errors.reopen_conflicts_with_open_conversation'));
            }

            throw $exception;
        }

        Log::info('[conversation] 会话已重新打开', [
            'conversation_id' => (string) $conversation->id,
            'actor_user_id' => (string) $actor->id,
        ]);

        $this->realtimeNotifier->conversationChanged($conversation, 'conversation_reopened', [
            'status' => ConversationStatus::Open->value,
            'inbox_status' => ConversationInboxStatus::TeammateHandling->value,
            'assigned_user_id' => (string) $actor->id,
        ]);

        return $conversation;
    }

    /**
     * 判断同一联系人和渠道下是否已有其它打开中的会话。
     */
    private function hasAnotherOpenConversation(Conversation $conversation): bool
    {
        // 同一联系人在同一渠道只允许一个打开中的会话。
        if ($conversation->contact_id === null || $conversation->channel_id === null) {
            return false;
        }

        return Conversation::query()

            ->where('contact_id', $conversation->contact_id)
            ->where('channel_id', $conversation->channel_id)
            ->where('status', ConversationStatus::Open)
            ->whereKeyNot($conversation->id)
            ->exists();
    }
}
