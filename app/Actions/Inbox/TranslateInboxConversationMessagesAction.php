<?php

namespace App\Actions\Inbox;

use App\Actions\Translation\TranslateConversationMessageAction;
use App\Data\CurrentUserContextData;
use App\Data\Inbox\FormQueueInboxMessageTranslationsData;
use App\Jobs\Translation\TranslateInboxConversationMessagesJob;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Translation\TranslationProviderPool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 把收件箱当前可见消息翻译成选定目标语言。
 */
class TranslateInboxConversationMessagesAction
{
    use AsAction;

    /**
     * 注入翻译供应商池和消息翻译用例。
     */
    public function __construct(
        private readonly TranslationProviderPool $translationPool,
        private readonly TranslateConversationMessageAction $translateAction,
    ) {}

    /**
     * 同步翻译指定消息到选定目标语言，返回实际翻译条数。
     *
     * @param  list<string>  $messageIds
     */
    public function handle(
        string $conversationId,
        array $messageIds,
        string $targetLocale,
        string $sourceLocale,
        bool $force = false,
    ): int {
        $conversation = Conversation::query()
            ->with('channel')
            ->find($conversationId);

        if ($conversation === null || $conversation->channel === null) {
            throw new NotFoundHttpException;
        }

        if (! $this->translationPool->hasUsable()) {
            Log::info('消息翻译已跳过：没有可用供应商', [
                'conversation_id' => $conversation->id,
                'source_locale' => $sourceLocale,
                'target_locale' => $targetLocale,
            ]);

            return 0;
        }

        $translated = 0;
        foreach ($this->messagesEligibleForTranslation($conversation, $messageIds, $targetLocale, $force) as $message) {
            if ($this->translateAction->handleForTargetLang(
                message: $message,
                conversation: $conversation,
                targetLang: $targetLocale,
                sourceLang: $sourceLocale,
                force: $force,
            )) {
                $translated++;
            }
        }

        return $translated;
    }

    /**
     * 校验消息 ID 并派发异步翻译任务。
     */
    public function asController(Request $request, string $conversationId): JsonResponse
    {
        CurrentUserContextData::fromRequest($request);
        $data = FormQueueInboxMessageTranslationsData::from($request);

        TranslateInboxConversationMessagesJob::dispatch(
            $conversationId,
            $data->message_ids,
            $data->target_locale,
            $data->source_locale,
            $data->force,
        )->afterCommit();

        return response()->json(['queued' => true]);
    }

    /**
     * 找出符合翻译条件的文本消息。
     *
     * @param  list<string>  $messageIds
     * @return Collection<int, ConversationMessage>
     */
    private function messagesEligibleForTranslation(Conversation $conversation, array $messageIds, string $targetLocale, bool $force): Collection
    {
        return ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('id', $messageIds)
            ->orderBy('seq_no')
            ->get()
            ->filter(function (ConversationMessage $message) use ($targetLocale, $force): bool {
                $payload = $message->payload ?? [];

                return $message->isEligibleForTranslation()
                    && ($force || ! isset($payload['translations'][$targetLocale]));
            })
            ->values();
    }
}
