<?php

namespace App\Actions\Inbox;

use App\Actions\Translation\TranslateConversationMessageAction;
use App\Data\CurrentUserContextData;
use App\Data\Inbox\FormTranslateInboxConversationPreviewsData;
use App\Jobs\Translation\TranslateInboxConversationPreviewsJob;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Translation\TranslationProviderPool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 翻译收件箱会话列表中各会话的最后一条消息。
 */
class TranslateInboxConversationPreviewsAction
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
     * 同步翻译指定会话的最后一条消息，返回实际翻译条数。
     *
     * @param  list<string>  $conversationIds
     */
    public function handle(array $conversationIds, string $targetLocale, string $sourceLocale): int
    {
        if (! $this->translationPool->hasUsable()) {
            Log::info('会话列表预览翻译已跳过：没有可用供应商', [
                'source_locale' => $sourceLocale,
                'target_locale' => $targetLocale,
            ]);

            return 0;
        }

        $conversations = Conversation::query()
            ->with(['channel', 'latestMessage'])
            ->whereIn('id', $conversationIds)
            ->get();

        $translated = 0;
        foreach ($conversations as $conversation) {
            $message = $conversation->latestMessage;
            if (! $this->shouldTranslate($message, $targetLocale)) {
                continue;
            }

            if ($this->translateAction->handleForTargetLang($message, $conversation, $targetLocale, $sourceLocale)) {
                $translated++;
            }
        }

        return $translated;
    }

    /**
     * 派发会话列表预览异步翻译任务。
     */
    public function asController(Request $request): JsonResponse
    {
        CurrentUserContextData::fromRequest($request);
        $data = FormTranslateInboxConversationPreviewsData::from($request);

        TranslateInboxConversationPreviewsJob::dispatch(
            $data->conversation_ids,
            $data->target_locale,
            $data->source_locale,
        )->afterCommit();

        return response()->json(['queued' => true]);
    }

    /**
     * 判断最后一条消息是否符合目标语言翻译条件。
     */
    private function shouldTranslate(?ConversationMessage $message, string $targetLocale): bool
    {
        return $message instanceof ConversationMessage
            && $message->isEligibleForTranslation()
            && ! isset($message->payload['translations'][$targetLocale]['text']);
    }
}
