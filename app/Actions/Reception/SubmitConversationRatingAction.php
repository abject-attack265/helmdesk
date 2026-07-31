<?php

namespace App\Actions\Reception;

use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationRatingHandledBy;
use App\Enums\ConversationRatingScore;
use App\Enums\ConversationStatus;
use App\Enums\MessageRole;
use App\Exceptions\BusinessException;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationMessage;
use App\Models\ConversationRating;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 保存会话满意度评价、时间线事件并推送实时更新。
 */
class SubmitConversationRatingAction
{
    use AsAction;

    /**
     * 注入接待实时通知器。
     */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 记录或更新已关闭且经过接待的会话评价。
     */
    public function handle(
        Conversation $conversation,
        ConversationRatingScore $score,
        ?string $comment = null,
    ): ConversationRating {
        $normalizedComment = $this->normalizeComment($comment);

        [$rating, $ratedConversation] = DB::transaction(function () use ($conversation, $score, $normalizedComment): array {
            $currentConversation = Conversation::query()
                ->whereKey($conversation->id)
                ->firstOrFail();

            if ($currentConversation->status !== ConversationStatus::Closed
                || $currentConversation->inbox_status === ConversationInboxStatus::TeammatePending) {
                throw new BusinessException(__('conversation.rating.errors.not_closed'));
            }

            $handledBy = $this->resolveHandledBy($currentConversation);
            $channelType = $currentConversation->channel()->firstOrFail()->type;

            $rating = ConversationRating::query()->updateOrCreate(
                ['conversation_id' => $currentConversation->id],
                [
                    'contact_id' => $currentConversation->contact_id,
                    'score' => $score,
                    'comment' => $normalizedComment,
                    'channel_type' => $channelType,
                    'handled_by' => $handledBy,
                    'assigned_user_id' => $currentConversation->assigned_user_id,
                    'rated_at' => now(),
                ],
            );

            ConversationEvent::query()->create([
                'conversation_id' => $currentConversation->id,
                'actor_user_id' => null,
                'type' => ConversationEventType::FeedbackReceived,
                'payload' => [
                    'score' => $score->value,
                    'comment' => $normalizedComment,
                ],
                'created_at' => now(),
            ]);

            return [$rating, $currentConversation];
        });

        Log::info('[reception] 会话评价已记录', [
            'conversation_id' => (string) $ratedConversation->id,
            'rating_id' => (string) $rating->id,
            'score' => $score->value,
            'handled_by' => $rating->handled_by->value,
            'channel_type' => $rating->channel_type->value,
        ]);

        $this->realtimeNotifier->conversationChanged($ratedConversation, 'rating_submitted', [
            'score' => $score->value,
        ]);

        return $rating;
    }

    /**
     * 根据坐席指派和客服消息判断评价归属。
     */
    private function resolveHandledBy(Conversation $conversation): ConversationRatingHandledBy
    {
        if ($conversation->assigned_user_id !== null) {
            return ConversationRatingHandledBy::Human;
        }

        $hasTeammateMessage = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', MessageRole::Teammate)
            ->exists();

        return $hasTeammateMessage
            ? ConversationRatingHandledBy::Human
            : ConversationRatingHandledBy::Ai;
    }

    /**
     * 去除评论首尾空白，并将空评论归一为 null。
     */
    private function normalizeComment(?string $comment): ?string
    {
        if ($comment === null) {
            return null;
        }

        $trimmed = trim($comment);

        return $trimmed === '' ? null : $trimmed;
    }
}
