<?php

namespace App\Actions\Inbox;

use App\Actions\Translation\TranslateConversationMessageAction;
use App\Data\CurrentUserContextData;
use App\Data\Inbox\FormPreviewInboxReplyTranslationData;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Conversation\ConversationReplyRule;
use App\Services\Translation\Exceptions\TranslationException;
use App\Services\Translation\TranslationText;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 生成收件箱客服回复的访客可见内容预览。
 */
class PreviewInboxReplyTranslationAction
{
    use AsAction;

    /**
     * 注入消息翻译用例。
     */
    public function __construct(
        private readonly TranslateConversationMessageAction $translateAction,
        private readonly ConversationReplyRule $replyRule,
    ) {}

    /**
     * 翻译客服待发送文本，供发送前确认访客内容。
     *
     * @return array{visitor_content: ?string, visitor_locale: ?string, source_locale: ?string}
     */
    public function handle(User $user, string $conversationId, string $content): array
    {
        $conversation = Conversation::query()->findOrFail($conversationId);
        $denialMessageKey = $this->replyRule->denialMessageKey($conversation, $user);

        if ($denialMessageKey !== null) {
            throw new AuthorizationException(__($denialMessageKey));
        }

        $targetLang = $conversation->visitor_locale;

        if (! TranslationText::hasTranslatableLetters($content)) {
            return [
                'visitor_content' => $content,
                'visitor_locale' => $targetLang,
                'source_locale' => $user->locale,
            ];
        }

        try {
            $result = $this->translateAction->translateContentForTargetLang($content, $targetLang);
        } catch (TranslationException $e) {
            Log::warning('客服回复翻译预览失败', [
                'user_id' => (string) $user->id,
                'conversation_id' => (string) $conversation->id,
                'target_locale' => $targetLang,
                'content_length' => mb_strlen($content),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return ['visitor_content' => null, 'visitor_locale' => $targetLang, 'source_locale' => null];
        }

        return [
            'visitor_content' => $result->text,
            'visitor_locale' => $result->target_lang,
            'source_locale' => $result->source_lang,
        ];
    }

    /**
     * 接收访客内容预览请求并返回 JSON。
     */
    public function asController(Request $request, string $conversationId): JsonResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);
        $data = FormPreviewInboxReplyTranslationData::from($request);

        return response()->json($this->handle($user, $conversationId, $data->content));
    }
}
