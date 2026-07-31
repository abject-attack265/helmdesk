<?php

namespace App\Actions\Reception;

use App\Data\Reception\ReceptionStateData;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Exceptions\BusinessException;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use App\Services\Reception\ChannelAiAvailability;
use App\Services\Reception\ReceptionStateBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/** 向接待会话追加 AI 回复消息并刷新会话状态。 */
class AppendAiMessageAction
{
    use AsAction;

    public const int MAX_CONTENT_LENGTH = 8000;

    /**
     * 注入实时通知和 AI 可用性服务。
     */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
        private readonly ChannelAiAvailability $aiAvailability,
    ) {}

    /** 在 AI 接待状态下追加文本回复，并保存引用、方案版本和接待轮次快照。 */
    public function handle(Conversation $conversation, string $content, ?string $quotedMessageId = null, ?string $receptionPlanVersionId = null, ?string $turnId = null): ReceptionStateData
    {
        $content = trim($content);
        if ($content === '') {
            throw ValidationException::withMessages(['content' => __('conversation.errors.empty_message')]);
        }
        if (Str::length($content) > self::MAX_CONTENT_LENGTH) {
            throw ValidationException::withMessages(['content' => __('conversation.errors.message_too_long')]);
        }

        [$conversation, $message, $channel] = DB::transaction(function () use ($conversation, $content, $quotedMessageId, $receptionPlanVersionId, $turnId): array {
            $lockedConversation = Conversation::query()
                ->with('channel')
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();
            $channel = $lockedConversation->channel
                ?? throw new BusinessException(__('conversation.errors.ai_reply_not_allowed'));

            if (
                $lockedConversation->status !== ConversationStatus::Open
                || $lockedConversation->inbox_status !== ConversationInboxStatus::AiHandling
                || ! $this->aiAvailability->canUseAi($channel)
            ) {
                throw new BusinessException(__('conversation.errors.ai_reply_not_allowed'));
            }

            [$aiSenderName] = ReceptionStateBuilder::channelMessageIdentity($channel, $lockedConversation);
            $resolvedQuotedMessageId = ConversationMessage::resolveQuotedMessageId(
                $lockedConversation->id,
                $quotedMessageId,
            );
            $message = ConversationMessage::query()->create([
                'conversation_id' => $lockedConversation->id,
                'role' => MessageRole::Ai,
                'kind' => MessageKind::Text,
                'content' => $content,
                'content_locale' => null,
                'sender_name' => $aiSenderName,
                'quoted_message_id' => $resolvedQuotedMessageId,
                'reception_plan_version_id' => $receptionPlanVersionId,
                'turn_id' => $turnId,
            ]);

            $lockedConversation->update([
                'last_message_at' => now(),
                'last_message_preview' => Conversation::messagePreview($content),
                'waiting_for_visitor_reply' => true,
                'unread_visitor_message_count' => 0,
            ]);

            Conversation::query()
                ->whereKey($lockedConversation->id)
                ->increment('unread_agent_message_count');

            return [$lockedConversation, $message, $channel];
        });

        $conversation->refresh();

        $this->realtimeNotifier->conversationChanged(
            $conversation,
            'ai_message_created',
            meta: $message->realtimeMeta(),
        );

        return ReceptionStateBuilder::build($channel, $conversation, '');
    }
}
