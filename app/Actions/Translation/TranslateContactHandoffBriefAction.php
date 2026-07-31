<?php

namespace App\Actions\Translation;

use App\Data\Contact\ContactHandoffBriefData;
use App\Data\Translation\MessageTranslationData;
use App\Enums\MessageTranslationOutcome;
use App\Models\Contact;
use App\Services\Contact\ContactAiContext;
use App\Services\Translation\Exceptions\TranslationException;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 为联系人接手简报追加客服视角翻译。
 */
class TranslateContactHandoffBriefAction
{
    use AsAction;

    /**
     * 注入通用文本翻译用例。
     */
    public function __construct(
        private readonly TranslateConversationMessageAction $translateAction,
    ) {}

    /**
     * 把联系人接手简报翻译到指定目标语言并写入 contacts.ai_context。
     *
     * $force 为手动重新翻译，跳过已存译文与缓存并覆盖结果。
     */
    public function handle(Contact $contact, string $targetLang, bool $force = false): MessageTranslationOutcome
    {
        $data = ContactHandoffBriefData::fromContext($contact->ai_context);
        if ($data === null) {
            return MessageTranslationOutcome::Skipped;
        }

        $context = $contact->ai_context;
        $briefData = $context['handoff_brief'];
        $translations = $data->translations;
        if (! $force && array_key_exists($targetLang, $translations)) {
            return MessageTranslationOutcome::Skipped;
        }

        try {
            $translations[$targetLang] = [
                'brief' => $this->translateString($data->brief, $targetLang, $force),
                'next_actions' => $this->translateList($data->next_actions, $targetLang, $force),
            ];
        } catch (TranslationException $exception) {
            Log::warning('联系人接手简报翻译失败', [
                'contact_id' => $contact->id,
                'target_lang' => $targetLang,
                'error_class' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return MessageTranslationOutcome::Failed;
        }

        $briefData['translations'] = $translations;
        $context['handoff_brief'] = $briefData;
        $contact->update(['ai_context' => ContactAiContext::normalize($context)]);

        Log::info('联系人接手简报翻译完成', [
            'contact_id' => (string) $contact->id,
            'target_lang' => $targetLang,
            'next_action_count' => count($data->next_actions),
            'force' => $force,
        ]);

        return MessageTranslationOutcome::Translated;
    }

    /**
     * 翻译接手简报正文。
     *
     * @return array<string, mixed>
     */
    private function translateString(string $value, string $targetLang, bool $force): array
    {
        $result = $this->translateAction->translateContentForTargetLang($value, $targetLang, force: $force);

        return MessageTranslationData::fromTranslationResult($result)->toArray();
    }

    /**
     * 按顺序翻译接手简报的下一步。
     *
     * @param  list<string>  $values
     * @return list<array<string, mixed>>
     */
    private function translateList(array $values, string $targetLang, bool $force): array
    {
        return array_map(
            fn (string $value): array => $this->translateString($value, $targetLang, $force),
            $values,
        );
    }
}
