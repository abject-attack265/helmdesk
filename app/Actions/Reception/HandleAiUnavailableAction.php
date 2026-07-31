<?php

namespace App\Actions\Reception;

use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\HandoffReason;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationMessage;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use App\Services\Reception\ReceptionPresetMessageTranslator;
use App\Services\Reception\ReceptionStateBuilder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 所有接待模型均不可用时，发送兜底文案并将会话转为人工待接，同时设置冷却期防止循环。
 */
class HandleAiUnavailableAction
{
    use AsAction;

    /**
     * 注入实时通知服务。
     */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
        private readonly ReceptionPresetMessageTranslator $messageTranslator,
    ) {}

    /**
     * 给访客发送 AI 不可用兜底文案，将会话切到 teammate_pending 并设置冷却时间戳。
     *
     * @return array{handled: bool}
     */
    public function handle(Conversation $conversation, string $notice): array
    {
        if ($conversation->inbox_status !== ConversationInboxStatus::AiHandling) {
            return ['handled' => false];
        }

        $channel = $conversation->channel;
        if ($channel === null) {
            return ['handled' => false];
        }

        [$aiSenderName] = ReceptionStateBuilder::channelMessageIdentity($channel, $conversation);
        $translation = $this->messageTranslator->translateForVisitor(
            conversation: $conversation,
            content: $notice,
            context: 'ai_unavailable_notice',
        );
        $visitorNotice = $translation['content'];

        $message = DB::transaction(function () use ($conversation, $visitorNotice, $translation, $aiSenderName): ConversationMessage {
            $message = ConversationMessage::query()->create([
                'conversation_id' => $conversation->id,
                'role' => MessageRole::Ai,
                'kind' => MessageKind::Text,
                'content' => $visitorNotice,
                'content_locale' => $translation['content_locale'],
                'payload' => $translation['payload'] !== null ? ['translations' => $translation['payload']] : null,
                'sender_name' => $aiSenderName,
            ]);

            ConversationEvent::query()->create([
                'conversation_id' => $conversation->id,
                'type' => ConversationEventType::HandoffRequested,
                'payload' => [
                    'reason' => HandoffReason::AiUnavailable->value,
                    'actor_kind' => 'system',
                    'reception_plan_version_id' => filled($conversation->reception_plan_version_id)
                        ? (string) $conversation->reception_plan_version_id
                        : null,
                ],
                'created_at' => now(),
            ]);

            $conversation->update([
                'assigned_user_id' => null,
                'inbox_status' => ConversationInboxStatus::TeammatePending,
                'last_message_at' => now(),
                'last_message_preview' => Conversation::messagePreview($visitorNotice),
                'waiting_for_visitor_reply' => false,
            ]);
            Conversation::query()
                ->whereKey($conversation->id)
                ->increment('unread_agent_message_count');

            return $message;
        });

        $conversation = $conversation->fresh();
        $this->realtimeNotifier->conversationChanged(
            $conversation,
            'handoff_requested',
            meta: $message->realtimeMeta(),
        );

        return ['handled' => true];
    }
}
