<?php

namespace App\Actions\AiChat;

use App\Data\AiChat\AiAssistantHistoryMatchData;
use App\Data\AiChat\AiAssistantHistorySearchData;
use App\Models\AiAssistantMessage;
use App\Models\AiAssistantThread;
use App\Models\Conversation;
use DateTimeZone;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 按关键词定位当前联系人的历史 AI 消息。
 */
class SearchAiAssistantHistoryAction
{
    use AsAction;

    private const int MAX_KEYWORD_LENGTH = 50;

    private const int DEFAULT_LIMIT = 5;

    private const int MAX_LIMIT = 10;

    private const int SUGGESTED_CONTEXT_MESSAGES = 12;

    /**
     * 在当前线程之外按多个字面关键词匹配历史消息，并返回可继续读取的位置。
     *
     * @param  list<string>  $keywords
     */
    public function handle(
        Conversation $conversation,
        string $currentThreadId,
        array $keywords,
        DateTimeZone $timezone,
        int $offset = 0,
        int $limit = self::DEFAULT_LIMIT,
    ): AiAssistantHistorySearchData {
        $conversation = Conversation::query()->find($conversation->id);

        if ($conversation === null) {
            throw new NotFoundHttpException;
        }

        $keywords = collect($keywords)
            ->filter(static fn (mixed $keyword): bool => is_string($keyword) && trim($keyword) !== '')
            ->map(static fn (string $keyword): string => mb_substr(trim($keyword), 0, self::MAX_KEYWORD_LENGTH))
            ->unique()
            ->take(5)
            ->values()
            ->all();

        if ($keywords === []) {
            return new AiAssistantHistorySearchData(
                offset: max(0, $offset),
                limit: max(1, min($limit, self::MAX_LIMIT)),
                has_more: false,
                matches: [],
            );
        }

        $offset = max(0, $offset);
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $threadIds = AiAssistantThread::query()
            ->forContactContext($conversation)
            ->where('id', '!=', $currentThreadId)
            ->pluck('id');

        if ($threadIds->isEmpty()) {
            return new AiAssistantHistorySearchData(
                offset: $offset,
                limit: $limit,
                has_more: false,
                matches: [],
            );
        }

        $matches = AiAssistantMessage::query()
            ->whereIn('thread_id', $threadIds)
            ->whereNotNull('content')
            ->where(function ($query) use ($keywords): void {
                foreach ($keywords as $keyword) {
                    $query->orWhereRaw('instr(lower(content), lower(?)) > 0', [$keyword]);
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit + 1)
            ->get();
        $hasMore = $matches->count() > $limit;

        $items = $matches
            ->take($limit)
            ->map(function (AiAssistantMessage $match) use ($keywords, $timezone): AiAssistantHistoryMatchData {
                $messageOffset = AiAssistantMessage::query()
                    ->where('thread_id', $match->thread_id)
                    ->where(function ($query) use ($match): void {
                        $query
                            ->where('created_at', '<', $match->created_at)
                            ->orWhere(function ($query) use ($match): void {
                                $query
                                    ->where('created_at', $match->created_at)
                                    ->where('id', '<', $match->id);
                            });
                    })
                    ->count();
                $matchedKeywords = array_values(array_filter(
                    $keywords,
                    static fn (string $keyword): bool => mb_stripos((string) $match->content, $keyword) !== false,
                ));

                return new AiAssistantHistoryMatchData(
                    thread_id: $match->thread_id,
                    message_offset: $messageOffset,
                    occurred_at: $match->created_at?->copy()->setTimezone($timezone)->toIso8601String() ?? '',
                    matched_keywords: $matchedKeywords,
                    preview: mb_substr((string) $match->content, 0, 300),
                    suggested_offset: max(0, $messageOffset - 4),
                    suggested_limit: self::SUGGESTED_CONTEXT_MESSAGES,
                );
            })
            ->all();

        return new AiAssistantHistorySearchData(
            offset: $offset,
            limit: $limit,
            has_more: $hasMore,
            matches: $items,
        );
    }
}
