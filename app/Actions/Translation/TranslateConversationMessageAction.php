<?php

namespace App\Actions\Translation;

use App\Data\Channel\Telegram\ChannelTelegramSettingsData;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Channel\WechatOfficialAccount\ChannelWechatOfficialAccountSettingsData;
use App\Data\Translation\MessageTranslationData;
use App\Enums\MessageRole;
use App\Enums\MessageTranslationOutcome;
use App\Enums\TranslationProviderSelectionStrategy;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Translation\Exceptions\TranslationException;
use App\Services\Translation\TranslationProviderPool;
use App\Services\Translation\TranslationResult;
use Illuminate\Support\Facades\Log;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 为会话消息生成指定源语言和目标语言的译文。
 *
 * 翻译结果写入 payload.translations[targetLang]，content_locale 记录 content 的实际语言。
 */
class TranslateConversationMessageAction
{
    use AsAction;

    /**
     * 注入全局翻译供应商轮询池。
     */
    public function __construct(
        private readonly TranslationProviderPool $pool,
    ) {}

    /**
     * 把指定消息翻译到目标语言。
     */
    public function handleForTargetLang(ConversationMessage $message, Conversation $conversation, string $targetLang, string $sourceLang = 'auto', bool $force = false): bool
    {
        return $this->handleForTargetLangWithOutcome($message, $conversation, $targetLang, $sourceLang, $force)->isTranslated();
    }

    /**
     * 把指定消息翻译到给定目标语言，并返回细分执行结果。
     */
    public function handleForTargetLangWithOutcome(ConversationMessage $message, Conversation $conversation, string $targetLang, string $sourceLang = 'auto', bool $force = false): MessageTranslationOutcome
    {
        if (! $message->isEligibleForTranslation() || ! filled($targetLang)) {
            return MessageTranslationOutcome::Skipped;
        }

        return $this->translateToTargetLang($message, $conversation, $targetLang, $sourceLang, $force);
    }

    /**
     * 把文本翻译成指定语言。
     */
    public function translateContentForTargetLang(
        string $content,
        string $targetLang,
        ?string $contextHint = null,
        array $callContext = [],
        string $sourceLang = 'auto',
        bool $force = false,
        TranslationProviderSelectionStrategy $strategy = TranslationProviderSelectionStrategy::MachineFirst,
        ?string $excludeProviderSlug = null,
    ): TranslationResult {
        $options = [];
        if (filled($contextHint)) {
            $options['context_hint'] = $contextHint;
        }
        if ($callContext !== []) {
            $options['call_context'] = $callContext;
        }

        return $this->pool->translate(
            content: $content,
            sourceLang: $sourceLang,
            targetLang: $targetLang,
            options: $options,
            force: $force,
            strategy: $strategy,
            excludeProviderSlug: $excludeProviderSlug,
        );
    }

    /**
     * 执行消息翻译并保存译文和识别出的原文语言。
     */
    private function translateToTargetLang(ConversationMessage $message, Conversation $conversation, string $targetLang, string $sourceLang = 'auto', bool $force = false): MessageTranslationOutcome
    {
        $content = (string) $message->content;
        $payload = $message->payload ?? [];
        if (! $force && isset($payload['translations'][$targetLang])) {
            return MessageTranslationOutcome::Skipped;
        }

        $settings = $this->channelTranslationSettings($conversation);
        $aiEnhanced = $message->role === MessageRole::Visitor
            && $settings?->visitor_message_ai_translation_enabled;
        $strategy = match (true) {
            $force => TranslationProviderSelectionStrategy::Random,
            $aiEnhanced => TranslationProviderSelectionStrategy::AiFirst,
            default => TranslationProviderSelectionStrategy::MachineFirst,
        };

        try {
            $result = $this->translateContentForTargetLang(
                content: $content,
                targetLang: $targetLang,
                contextHint: $aiEnhanced ? $settings?->translation_context_hint : null,
                callContext: [
                    'conversation_id' => (string) $conversation->id,
                    'conversation_message_id' => (string) $message->id,
                ],
                sourceLang: $sourceLang,
                force: $force,
                strategy: $strategy,
                excludeProviderSlug: $force ? $this->currentProviderSlug($payload, $targetLang) : null,
            );
        } catch (TranslationException $exception) {
            Log::warning('消息翻译失败', [
                'conversation_id' => (string) $conversation->id,
                'message_id' => (string) $message->id,
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
                'selection_strategy' => $strategy->value,
                'force' => $force,
                'error_class' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return MessageTranslationOutcome::Failed;
        }

        $updates = [];

        if ($message->content_locale === null) {
            $updates['content_locale'] = $result->source_lang;
        }

        $shouldStoreTranslation = trim($result->text) !== trim($content);

        if ($shouldStoreTranslation) {
            $payload['translations'][$result->target_lang] = MessageTranslationData::fromTranslationResult($result)->toArray();
            $updates['payload'] = $payload;
        }

        $message->update($updates);

        return MessageTranslationOutcome::Translated;
    }

    /**
     * 返回支持消息翻译配置的渠道设置。
     */
    private function channelTranslationSettings(
        Conversation $conversation,
    ): ChannelWebSettingsData|ChannelTelegramSettingsData|ChannelWechatOfficialAccountSettingsData|null {
        if ($conversation->channel === null) {
            return null;
        }

        $settings = $conversation->channel->settings;

        return match (true) {
            $settings instanceof ChannelWebSettingsData,
            $settings instanceof ChannelTelegramSettingsData,
            $settings instanceof ChannelWechatOfficialAccountSettingsData => $settings,
            default => throw new LogicException('渠道设置不支持消息翻译。'),
        };
    }

    /**
     * 返回当前目标语言译文的供应商标识。
     *
     * @param  array<string, mixed>  $payload
     */
    private function currentProviderSlug(array $payload, string $targetLang): ?string
    {
        return $payload['translations'][$targetLang]['provider_slug'] ?? null;
    }
}
