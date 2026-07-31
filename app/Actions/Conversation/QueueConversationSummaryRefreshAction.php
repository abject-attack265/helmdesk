<?php

namespace App\Actions\Conversation;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Jobs\Conversation\GenerateConversationSummaryJob;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 按内置阈值决定是否排队刷新会话摘要。
 */
class QueueConversationSummaryRefreshAction
{
    use AsAction;

    private const int MESSAGE_STEP = 8;

    /**
     * 有足够新增文本或调用方强制要求时，异步刷新会话摘要。
     */
    public function handle(Conversation $conversation, bool $force = false): void
    {
        if (config('queue.default') === 'sync') {
            Log::debug('会话摘要刷新被 sync 队列跳过', [
                'conversation_id' => (string) $conversation->id,
                'force' => $force,
            ]);

            return;
        }

        if (! $force && ! $this->hasEnoughNewMessages($conversation)) {
            return;
        }

        GenerateConversationSummaryJob::dispatch((string) $conversation->id, $force)
            ->afterCommit()
            ->delay(now()->addSeconds($force ? 2 : 8));
    }

    /**
     * 判断从上次摘要覆盖水位以后是否新增了足够多文本消息。
     */
    private function hasEnoughNewMessages(Conversation $conversation): bool
    {
        $coveredSeqNo = (int) $conversation->summary_last_message_seq_no;

        return ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('seq_no', '>', $coveredSeqNo)
            ->whereIn('role', [MessageRole::Visitor, MessageRole::Ai, MessageRole::Teammate])
            ->where('kind', MessageKind::Text)
            ->whereNotNull('content')
            ->whereNull('recalled_at')
            ->count() >= self::MESSAGE_STEP;
    }
}
