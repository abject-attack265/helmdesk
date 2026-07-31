<?php

namespace App\Actions\Contact;

use App\Actions\Conversation\CollectConversationLlmContextAction;
use App\Data\Contact\GeneratedContactHandoffBriefData;
use App\Enums\AiCallPurpose;
use App\Enums\AiModelPurpose;
use App\Models\Contact;
use App\Models\Conversation;
use App\Services\Ai\NeuronStructuredGenerator;
use App\Services\Ai\Schemas\ContactHandoffBriefSchema;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\AiRuntime\AiModelPool;
use App\Services\Contact\ContactAiContext;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use App\Support\StructuredOutputNormalizer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * 基于当前会话和相关历史生成坐席接手简报。
 */
class GenerateContactHandoffBriefAction
{
    use AsAction;

    private const int MAX_HISTORY_CONVERSATIONS = 3;

    private const int BRIEF_MAX_LENGTH = 240;

    private const int NEXT_ACTION_MAX_ITEMS = 2;

    private const int NEXT_ACTION_MAX_LENGTH = 100;

    /**
     * 配置接手简报生成依赖。
     */
    public function __construct(
        private readonly NeuronStructuredGenerator $generator,
        private readonly AiModelPool $aiModelPool,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 根据指定会话更新联系人接手简报并通知收件箱刷新。
     */
    public function handle(Contact $contact, Conversation $conversation): ?array
    {
        $messages = CollectConversationLlmContextAction::run($conversation);
        if ($messages === []) {
            Log::info('[ai] 联系人接手简报跳过：当前会话没有文本消息', [
                'contact_id' => (string) $contact->id,
                'conversation_id' => (string) $conversation->id,
            ]);

            return null;
        }

        $history = $this->conversationHistory($contact, $conversation);
        $locale = $conversation->visitor_locale;
        $result = $this->generateWithCandidates($contact, $conversation, $locale, $messages, $history);
        $context = $this->mergeHandoffBrief($contact->ai_context, $result, $locale);

        $contact->forceFill([
            'ai_context' => ContactAiContext::normalize($context),
        ])->save();

        Log::info('[ai] 联系人接手简报生成完成', [
            'contact_id' => (string) $contact->id,
            'conversation_id' => (string) $conversation->id,
            'message_count' => count($messages),
            'history_count' => count($history),
            'brief_length' => Str::length($result->brief),
            'next_action_count' => count($result->next_actions),
        ]);

        $this->realtimeNotifier->conversationChanged(
            $conversation,
            'contact_handoff_brief_updated',
            meta: ['contact_id' => (string) $contact->id],
        );

        return $contact->ai_context;
    }

    /**
     * 收集当前会话之外有限数量的历史会话摘要。
     *
     * @return list<array<string, mixed>>
     */
    private function conversationHistory(Contact $contact, Conversation $conversation): array
    {
        return Conversation::query()
            ->where('contact_id', $contact->id)
            ->whereKeyNot($conversation->id)
            ->whereNotNull('summary')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::MAX_HISTORY_CONVERSATIONS)
            ->get(['id', 'summary', 'created_at', 'closed_at'])
            ->reverse()
            ->values()
            ->map(
                static fn (Conversation $conversation): array => [
                    'id' => (string) $conversation->id,
                    'summary' => (string) $conversation->summary,
                    'occurred_at' => $conversation->closed_at?->toIso8601String() ?? $conversation->created_at?->toIso8601String(),
                ],
            )
            ->values()
            ->all();
    }

    /**
     * 按模型池顺序生成接手简报，候选失败时继续使用下一个模型。
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array<string, mixed>>  $history
     */
    private function generateWithCandidates(
        Contact $contact,
        Conversation $conversation,
        string $locale,
        array $messages,
        array $history,
    ): GeneratedContactHandoffBriefData {
        $lastError = null;

        foreach ($this->aiModelPool->modelsForPurpose(AiModelPurpose::BackgroundTask) as $model) {
            try {
                $schema = $this->generator->generate(
                    $model,
                    $this->contactInstructions($locale),
                    $this->buildUserMessage($messages, $history),
                    ContactHandoffBriefSchema::class,
                    AiUsageContext::forModel(
                        $model,
                        (string) $conversation->id,
                        AiCallPurpose::ContactHandoffBrief,
                        contactId: (string) $contact->id,
                    ),
                );

                return new GeneratedContactHandoffBriefData(
                    brief: $this->normalizeRequiredText($schema->brief, self::BRIEF_MAX_LENGTH),
                    next_actions: $this->normalizeNextActions($schema->next_actions),
                );
            } catch (Throwable $exception) {
                $lastError = $exception;
                Log::warning('联系人接手简报生成候选模型失败', [
                    'contact_id' => $contact->id,
                    'conversation_id' => $conversation->id,
                    'ai_model_id' => $model->id,
                    'provider' => $model->provider?->name,
                    'model' => $model->name,
                    'error_class' => $exception::class,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        throw $lastError ?? new \RuntimeException('No available contact handoff brief model.');
    }

    /**
     * 返回限制信息范围和输出长度的接手简报生成指令。
     */
    private function contactInstructions(string $locale): string
    {
        return '你是客服坐席接手助手。请用'.$locale.'语言输出一句接手简报和最多两项下一步。'
            .'简报只说明当前诉求、必要状态和关键背景，不超过 240 个字符。'
            .'历史会话只有与当前诉求直接相关时才能引用，不要罗列时间线，不要描述沟通风格，'
            .'不要把没有回复、没有后续信息或结果未知推断为未解决。'
            .'下一步必须是坐席可以立即执行的动作，每项不超过 100 个字符；问题已解决或没有明确动作时返回空列表。'
            .'不要编造会话中未出现的信息。';
    }

    /**
     * 把当前会话消息与历史摘要拼成结构化抽取输入。
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array<string, mixed>>  $history
     */
    private function buildUserMessage(array $messages, array $history): string
    {
        $lines = array_map(
            static fn (array $message): string => $message['role'].'：'.$message['content'],
            $messages,
        );
        $text = "# 当前会话\n\n".implode("\n", $lines);

        if ($history !== []) {
            $text .= "\n\n# 可选历史背景\n\n".json_encode(
                $history,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
            );
        }

        return $text;
    }

    /**
     * 压缩单行文本并限制字符数。
     */
    private function normalizeText(string $text, int $maxLength): string
    {
        return Str::limit(Str::squish($text), $maxLength, '');
    }

    /**
     * 压缩并限制必填文本，空内容作为无效模型输出处理。
     */
    private function normalizeRequiredText(string $text, int $maxLength): string
    {
        $normalized = $this->normalizeText($text, $maxLength);
        if ($normalized === '') {
            throw new \UnexpectedValueException('联系人接手简报不能为空。');
        }

        return $normalized;
    }

    /**
     * 清理、去重并限制下一步数量和长度。
     *
     * @param  list<string>  $actions
     * @return list<string>
     */
    private function normalizeNextActions(array $actions): array
    {
        $normalized = array_map(
            fn (string $action): string => $this->normalizeText($action, self::NEXT_ACTION_MAX_LENGTH),
            StructuredOutputNormalizer::stringList($actions),
        );

        return array_slice(array_values(array_unique($normalized)), 0, self::NEXT_ACTION_MAX_ITEMS);
    }

    /**
     * 把接手简报合并到联系人 AI 上下文。
     *
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>
     */
    private function mergeHandoffBrief(?array $context, GeneratedContactHandoffBriefData $result, string $locale): array
    {
        $context ??= [];
        $context['handoff_brief'] = [
            'brief' => $result->brief,
            'next_actions' => $result->next_actions,
            'source_locale' => $locale,
            'translations' => [],
            'updated_at' => now()->toIso8601String(),
        ];

        return $context;
    }
}
