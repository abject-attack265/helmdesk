<?php

namespace App\Actions\AiChat;

use App\Data\AiChat\AiAssistantHistoryPageData;
use App\Models\AiAssistantMessage;
use App\Models\AiAssistantThread;
use App\Models\Conversation;
use DateTimeZone;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 按消息位置读取当前联系人的一段历史 AI 线程。
 */
class ReadAiAssistantHistoryAction
{
    use AsAction;

    private const int DEFAULT_LIMIT = 12;

    private const int MAX_LIMIT = 30;

    /**
     * 从指定 offset 开始返回连续消息，并提供剩余页状态。
     */
    public function handle(
        Conversation $conversation,
        string $currentThreadId,
        string $threadId,
        DateTimeZone $timezone,
        int $offset = 0,
        int $limit = self::DEFAULT_LIMIT,
    ): AiAssistantHistoryPageData {
        $conversation = Conversation::query()->find($conversation->id);

        if ($conversation === null) {
            throw new NotFoundHttpException;
        }

        $thread = AiAssistantThread::query()
            ->forContactContext($conversation)
            ->where('id', '!=', $currentThreadId)
            ->whereKey($threadId)
            ->first();

        if ($thread === null) {
            throw new NotFoundHttpException;
        }

        $offset = max(0, $offset);
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $totalMessages = $thread->messages()->count();
        $messages = $thread->messages()
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->values()
            ->map(static fn (AiAssistantMessage $message, int $index): array => [
                'offset' => $offset + $index,
                'round_id' => $message->round_id,
                'role' => $message->role->value,
                'content' => $message->content ?? '',
                'occurred_at' => $message->created_at?->copy()->setTimezone($timezone)->toIso8601String() ?? '',
            ])
            ->all();

        return new AiAssistantHistoryPageData(
            thread_id: $thread->id,
            offset: $offset,
            limit: $limit,
            total_messages: $totalMessages,
            has_more: $offset + count($messages) < $totalMessages,
            messages: $messages,
        );
    }
}
