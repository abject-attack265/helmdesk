<?php

namespace App\Actions\AiChat;

use App\Data\AiChat\AiAssistantHistoryCatalogData;
use App\Data\AiChat\AiAssistantHistoryThreadData;
use App\Models\AiAssistantThread;
use App\Models\Conversation;
use DateTimeZone;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 列出当前联系人可供 Agent 检索的历史 AI 线程目录。
 */
class ListAiAssistantHistoryThreadsAction
{
    use AsAction;

    private const int MAX_LISTED_THREADS = 50;

    /**
     * 返回当前线程之外的历史线程数量和最近线程元数据。
     */
    public function handle(
        Conversation $conversation,
        string $currentThreadId,
        DateTimeZone $timezone,
    ): AiAssistantHistoryCatalogData {
        $conversation = Conversation::query()->find($conversation->id);

        if ($conversation === null) {
            throw new NotFoundHttpException;
        }

        $query = AiAssistantThread::query()
            ->forContactContext($conversation)
            ->where('id', '!=', $currentThreadId);
        $totalThreads = (clone $query)->count();
        $threads = $query
            ->withCount('messages')
            ->withMax('messages', 'created_at')
            ->withCasts(['messages_max_created_at' => 'datetime'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_LISTED_THREADS)
            ->get()
            ->map(static fn (AiAssistantThread $thread): AiAssistantHistoryThreadData => new AiAssistantHistoryThreadData(
                id: $thread->id,
                message_count: (int) $thread->messages_count,
                started_at: $thread->created_at?->copy()->setTimezone($timezone)->toIso8601String() ?? '',
                last_message_at: $thread->messages_max_created_at?->copy()->setTimezone($timezone)->toIso8601String()
                    ?? $thread->created_at?->copy()->setTimezone($timezone)->toIso8601String()
                    ?? '',
            ))
            ->all();

        return new AiAssistantHistoryCatalogData(
            total_threads: $totalThreads,
            threads: $threads,
        );
    }
}
