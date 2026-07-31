<?php

namespace App\Actions\Reception;

use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 每分钟定时扫描：把排队/坐席无响应超时的会话交回 AI 接待，并补答积压的访客消息，
 * 覆盖访客沉默、无消息触发即时评估的场景。
 *
 * 调度上排在 reception:close-idle-conversations 之前：同一分钟内先接管再关单，
 * 保证到期会话优先交给 AI 而不是被空闲关单收走。
 */
class TakeOverOverdueReceptionConversationsAction
{
    use AsAction;

    /**
     * 注入单会话接管、补答与实时通知。
     */
    public function __construct(
        private readonly TakeOverReceptionConversationByAiAction $takeOverByAi,
        private readonly DispatchAiCatchUpTurnAction $dispatchAiCatchUpTurn,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 扫描开放中的排队/人工接待会话，执行到期接管；返回接管数。
     *
     * @return array{taken_over: int}
     */
    public function handle(): array
    {
        $takenOver = 0;

        Conversation::query()
            ->where('status', ConversationStatus::Open)
            ->whereIn('inbox_status', [
                ConversationInboxStatus::TeammatePending,
                ConversationInboxStatus::TeammateHandling,
            ])
            ->whereNotNull('channel_id')
            ->with('channel')
            ->chunkById(200, function (Collection $conversations) use (&$takenOver): void {
                foreach ($conversations as $conversation) {
                    // 渠道已暂停（软删）时关系解析为 null，跳过不接管。
                    $channel = $conversation->channel;
                    if ($channel === null) {
                        continue;
                    }

                    if (! $this->takeOverByAi->handle($channel, $conversation)) {
                        continue;
                    }

                    $takenOver++;
                    $this->realtimeNotifier->conversationChanged($conversation, 'conversation_taken_over_by_ai', [
                        'inbox_status' => $conversation->inbox_status->value,
                    ]);
                    $this->dispatchAiCatchUpTurn->handle($conversation);
                }
            });

        return ['taken_over' => $takenOver];
    }
}
