<?php

namespace App\Actions\Translation;

use App\Data\Translation\MessageTranslationData;
use App\Enums\MessageTranslationOutcome;
use App\Models\Conversation;
use App\Services\Translation\Exceptions\TranslationException;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 为会话摘要生成指定源语言和目标语言的译文。
 */
class TranslateConversationSummaryAction
{
    use AsAction;

    /**
     * 注入通用文本翻译用例。
     */
    public function __construct(
        private readonly TranslateConversationMessageAction $translateAction,
    ) {}

    /**
     * 把会话摘要翻译到指定目标语言并写入 summary_translations。
     */
    public function handle(Conversation $conversation, string $targetLang, string $sourceLang, bool $force = false): MessageTranslationOutcome
    {
        if (! filled($conversation->summary) || ! filled($targetLang)) {
            return MessageTranslationOutcome::Skipped;
        }

        $translations = $conversation->summary_translations ?? [];
        if (! $force && isset($translations[$targetLang]['text']) && is_string($translations[$targetLang]['text'])) {
            return MessageTranslationOutcome::Skipped;
        }

        try {
            $result = $this->translateAction->translateContentForTargetLang(
                content: (string) $conversation->summary,
                targetLang: $targetLang,
                sourceLang: $sourceLang,
                force: $force,
            );
        } catch (TranslationException $exception) {
            Log::warning('会话摘要翻译失败', [
                'conversation_id' => $conversation->id,
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
                'error' => $exception->getMessage(),
            ]);

            return MessageTranslationOutcome::Failed;
        }

        // 保存识别出的源语言，供界面判断摘要是否需要译文。
        $updates = ['summary_locale' => $conversation->summary_locale ?? $result->source_lang];

        // 目标语言与原文一致时只保存源语言，避免重复存储相同文本。
        if (trim($result->text) !== trim((string) $conversation->summary)) {
            $translations[$targetLang] = MessageTranslationData::fromTranslationResult($result)->toArray();
            $updates['summary_translations'] = $translations;
        }

        $conversation->update($updates);

        return MessageTranslationOutcome::Translated;
    }
}
