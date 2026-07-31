<?php

namespace App\Actions\Reception;

use App\Actions\Conversation\QueueConversationSummaryRefreshAction;
use App\Enums\ChannelType;
use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Exceptions\BusinessException;
use App\Jobs\Telegram\SendTelegramRatingPromptJob;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\User;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 关闭会话并写入状态变更事件。
 */
class CloseConversationAction
{
    use AsAction;

    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    public function handle(
        Conversation $conversation,
        ?User $actor = null,
    ): Conversation {
        $conversation = DB::transaction(function () use ($conversation, $actor): Conversation {
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedConversation->status === ConversationStatus::Closed) {
                throw new BusinessException(__('conversation.errors.already_closed'));
            }

            if ($actor !== null
                && $lockedConversation->assigned_user_id !== null
                && (string) $lockedConversation->assigned_user_id !== (string) $actor->id) {
                throw new BusinessException(__('conversation.errors.close_not_allowed_for_assignee'));
            }

            $lockedConversation->update([
                'status' => ConversationStatus::Closed,
                'waiting_for_visitor_reply' => false,
                'closed_at' => now(),
            ]);

            ConversationEvent::query()->create([
                'conversation_id' => $lockedConversation->id,
                'actor_user_id' => $actor?->id,
                'type' => ConversationEventType::StatusChanged,
                'payload' => [
                    'status' => ConversationStatus::Closed->value,
                ],
                'created_at' => now(),
            ]);

            return $lockedConversation->fresh();
        });

        Log::info('[conversation] 会话已关闭', [
            'conversation_id' => (string) $conversation->id,
            'actor_user_id' => $actor !== null ? (string) $actor->id : null,
        ]);

        $this->realtimeNotifier->conversationChanged($conversation, 'conversation_closed', [
            'status' => ConversationStatus::Closed->value,
        ]);
        // 关单后只自动生成一次会话总结；标签跟随总结完成后再生成，避免消息追加阶段多次打标。
        QueueConversationSummaryRefreshAction::run($conversation, force: true);

        // Telegram 渠道在关单后推送满意度评价按钮；Web 渠道由访客端 state.can_rate 驱动评价卡，无需此推送。
        // 排队中无人接待过的会话不邀评。
        if ($conversation->inbox_status !== ConversationInboxStatus::TeammatePending
            && $conversation->channel?->type === ChannelType::Telegram) {
            SendTelegramRatingPromptJob::dispatch((string) $conversation->id)->afterCommit();
        }

        return $conversation;
    }
}
