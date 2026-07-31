<?php

namespace App\Actions\Inbox;

use App\Actions\Translation\TranslateConversationSummaryAction;
use App\Data\CurrentUserContextData;
use App\Data\Inbox\FormQueueInboxConversationSummaryTranslationsData;
use App\Jobs\Translation\TranslateInboxConversationSummariesJob;
use App\Models\Conversation;
use App\Services\Translation\TranslationProviderPool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 为收件箱当前联系人的会话摘要生成目标语言译文。
 */
class TranslateInboxConversationSummariesAction
{
    use AsAction;

    /**
     * 注入翻译供应商池和会话摘要翻译用例。
     */
    public function __construct(
        private readonly TranslationProviderPool $translationPool,
        private readonly TranslateConversationSummaryAction $translateAction,
    ) {}

    /**
     * 同步翻译指定会话摘要到选定目标语言，返回实际翻译条数。
     *
     * @param  list<string>  $conversationIds
     */
    public function handle(
        string $conversationId,
        array $conversationIds,
        string $targetLocale,
        string $sourceLocale,
        bool $force = false,
    ): int {
        $anchor = Conversation::query()
            ->find($conversationId);

        if ($anchor === null || $anchor->contact_id === null) {
            throw new NotFoundHttpException;
        }

        if (! $this->translationPool->hasUsable()) {
            Log::info('会话摘要翻译已跳过：没有可用供应商', [
                'conversation_id' => $anchor->id,
                'source_locale' => $sourceLocale,
                'target_locale' => $targetLocale,
            ]);

            return 0;
        }

        $translated = 0;
        foreach ($this->summariesNeedingTranslation((string) $anchor->contact_id, $conversationIds, $targetLocale, $force) as $conversation) {
            if ($this->translateAction->handle($conversation, $targetLocale, $sourceLocale, $force)->isTranslated()) {
                $translated++;
            }
        }

        return $translated;
    }

    /**
     * 派发会话摘要异步翻译任务。
     */
    public function asController(Request $request, string $conversationId): JsonResponse
    {
        CurrentUserContextData::fromRequest($request);
        $data = FormQueueInboxConversationSummaryTranslationsData::from($request);

        TranslateInboxConversationSummariesJob::dispatch(
            $conversationId,
            $data->conversation_ids,
            $data->target_locale,
            $data->source_locale,
            $data->force,
        )->afterCommit();

        return response()->json(['queued' => true]);
    }

    /**
     * 找出缺少选定目标语言译文的会话摘要。
     *
     * @param  list<string>  $conversationIds
     * @return Collection<int, Conversation>
     */
    private function summariesNeedingTranslation(string $contactId, array $conversationIds, string $targetLocale, bool $force = false): Collection
    {
        return Conversation::query()
            ->where('contact_id', $contactId)
            ->whereIn('id', $conversationIds)
            ->whereNotNull('summary')
            ->get(['id', 'contact_id', 'reception_plan_version_id', 'summary', 'summary_locale', 'summary_translations'])
            ->filter(function (Conversation $conversation) use ($targetLocale, $force): bool {
                if ($force) {
                    return true;
                }

                $translations = $conversation->summary_translations ?? [];

                return ! isset($translations[$targetLocale]['text']);
            })
            ->values();
    }
}
