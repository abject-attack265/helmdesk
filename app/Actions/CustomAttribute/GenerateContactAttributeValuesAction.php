<?php

namespace App\Actions\CustomAttribute;

use App\Actions\Conversation\CollectConversationLlmContextAction;
use App\Enums\AiCallPurpose;
use App\Enums\AttributeValueSource;
use App\Models\AttributeDefinition;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\ContactAttributeValue;
use App\Models\Conversation;
use App\Services\Ai\NeuronStructuredGenerator;
use App\Services\Ai\Schemas\ContactAttributeExtractionSchema;
use App\Services\Ai\Schemas\ContactAttributePickSchema;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\Conversation\ConversationLlmCandidateResolver;
use App\Services\CustomAttribute\AttributeValueNormalizer;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * 从单次会话内容中提取联系人自定义属性值并落库（AI 自动写入）。
 *
 * 只处理 is_ai_writable=true 的属性定义；写入策略保守：
 * - 已有人工/API/导入等非 AI 来源的值一律不覆盖（这些定义不进候选清单）；
 * - AI 只填空值或刷新此前由 AI 写入的值，从不清空已有值；
 * - 提取值按属性类型二次校验，不合法或低置信的单条建议直接丢弃。
 */
class GenerateContactAttributeValuesAction
{
    use AsAction;

    private const float CONFIDENCE_THRESHOLD = 0.5;

    /**
     * 注入结构化生成原语、候选模型解析与实时通知服务。
     */
    public function __construct(
        private readonly NeuronStructuredGenerator $generator,
        private readonly ConversationLlmCandidateResolver $candidates,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 提取并写入当前会话联系人的 AI 可写属性。
     */
    public function handle(Conversation $conversation): void
    {
        $conversation->loadMissing('contact');

        $contact = $conversation->contact;
        if (! $contact instanceof Contact) {
            return;
        }

        $existingValues = ContactAttributeValue::query()

            ->where('contact_id', $contact->id)
            ->get()
            ->keyBy('definition_id');

        $definitions = $this->resolveWritableDefinitions($conversation, $existingValues);
        if ($definitions->isEmpty()) {
            return;
        }

        $messages = CollectConversationLlmContextAction::run($conversation);
        if ($messages === [] && blank($conversation->summary)) {
            return;
        }

        $picks = $this->generateWithCandidates($conversation, $contact, $definitions, $messages);
        $this->applyPicks($conversation, $contact, $definitions, $existingValues, $picks);
    }

    /**
     * 取允许 AI 写入、且当前值可被 AI 更新（空值或此前由 AI 写入）的属性定义，按 key 索引。
     *
     * @param  Collection<string, ContactAttributeValue>  $existingValues
     * @return Collection<string, AttributeDefinition>
     */
    private function resolveWritableDefinitions(Conversation $conversation, Collection $existingValues): Collection
    {
        return AttributeDefinition::query()

            ->where('is_ai_writable', true)
            ->active()
            ->ordered()
            ->get()
            ->filter(function (AttributeDefinition $definition) use ($existingValues): bool {
                $existing = $existingValues->get($definition->id);

                return $existing === null || $existing->source === AttributeValueSource::Ai;
            })
            ->keyBy('key');
    }

    /**
     * 校验 AI 建议并落库：阈值过滤、类型校验、来源与置信度标记、活动日志与实时通知。
     *
     * @param  Collection<string, AttributeDefinition>  $definitions
     * @param  Collection<string, ContactAttributeValue>  $existingValues
     * @param  list<ContactAttributePickSchema>  $picks
     */
    private function applyPicks(
        Conversation $conversation,
        Contact $contact,
        Collection $definitions,
        Collection $existingValues,
        array $picks,
    ): void {
        $changed = [];

        DB::transaction(function () use ($conversation, $contact, $definitions, $existingValues, $picks, &$changed) {
            foreach ($picks as $pick) {
                if ($pick->confidence < self::CONFIDENCE_THRESHOLD) {
                    continue;
                }

                $definition = $definitions->get(trim($pick->key));
                if ($definition === null) {
                    continue;
                }

                $rawValue = $definition->type->usesOptions() && $pick->values !== []
                    ? $pick->values
                    : $pick->value;

                $normalizedValue = AttributeValueNormalizer::normalize($definition, $rawValue);

                // AI 只负责补充和刷新，空值建议不落库也不清除已有值。
                if (AttributeValueNormalizer::isEmpty($definition, $normalizedValue)) {
                    continue;
                }

                if (! AttributeValueNormalizer::isValid($definition, $normalizedValue)) {
                    Log::info('联系人属性 AI 提取值不符合类型约束，已丢弃', [
                        'conversation_id' => $conversation->id,
                        'contact_id' => $contact->id,
                        'attribute_key' => $definition->key,
                    ]);

                    continue;
                }

                $existing = $existingValues->get($definition->id);
                $oldValue = $existing?->value();
                $payload = [
                    'value_json' => ['value' => $normalizedValue],
                    'source' => AttributeValueSource::Ai,
                    'confidence' => round(min(max($pick->confidence, 0.0), 1.0), 4),
                    'updated_by_user_id' => null,
                ];

                if ($existing !== null) {
                    if ($oldValue !== $normalizedValue) {
                        $changed[] = ['key' => $definition->key, 'old' => $oldValue, 'new' => $normalizedValue];
                    }
                    $existing->update($payload);
                } else {
                    $changed[] = ['key' => $definition->key, 'old' => null, 'new' => $normalizedValue];
                    ContactAttributeValue::query()->create($payload + [
                        'contact_id' => $contact->id,
                        'definition_id' => $definition->id,
                    ]);
                }
            }

            if ($changed !== []) {
                ContactActivityLog::query()->create([
                    'contact_id' => $contact->id,
                    'actor_user_id' => null,
                    'action' => 'custom_attributes_updated',
                    'created_at' => now(),
                    'payload' => ['changed' => $changed, 'source' => AttributeValueSource::Ai->value],
                ]);
            }
        });

        Log::info('[ai] 联系人属性 AI 提取完成', [
            'conversation_id' => (string) $conversation->id,
            'contact_id' => (string) $contact->id,
            'written_count' => count($changed),
        ]);

        if ($changed !== []) {
            $this->realtimeNotifier->conversationChanged(
                $conversation->refresh(),
                'contact_attributes_updated',
                meta: ['contact_id' => (string) $contact->id],
            );
        }
    }

    /**
     * 按候选模型顺序做结构化提取（首个成功即返回）。
     *
     * @param  Collection<string, AttributeDefinition>  $definitions
     * @param  list<array{role: string, content: string}>  $messages
     * @return list<ContactAttributePickSchema>
     */
    private function generateWithCandidates(Conversation $conversation, Contact $contact, Collection $definitions, array $messages): array
    {
        $lastError = null;

        foreach ($this->candidates->resolve($conversation) as $model) {
            try {
                $extraction = $this->generator->generate(
                    $model,
                    $this->extractionInstructions($conversation->visitor_locale),
                    $this->buildUserMessage($definitions, $conversation->summary, $messages),
                    ContactAttributeExtractionSchema::class,
                    AiUsageContext::forModel($model, (string) $conversation->id, AiCallPurpose::ContactAttributes, contactId: (string) $contact->id),
                );

                return array_values($extraction->picks);
            } catch (Throwable $exception) {
                $lastError = $exception;
                Log::warning('联系人属性 AI 提取候选模型失败', [
                    'conversation_id' => $conversation->id,
                    'ai_model_id' => $model->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($lastError !== null) {
            throw $lastError;
        }

        return [];
    }

    /**
     * 属性提取指令：只提取会话内容明确支持的值，并约束各类型的输出格式。
     */
    private function extractionInstructions(string $locale): string
    {
        return '你是客服联系人画像助手。请根据会话内容，为联系人提取候选属性清单中列出的属性值。'
            .'key 必须严格来自候选清单；只提取会话内容明确支持的值，不要凭空猜测，没有依据的属性不要出现在结果里。'
            .'值的格式按属性类型：文本用'.$locale.'语言原样记录；数字只填纯数字；日期填 YYYY-MM-DD；'
            .'布尔填 true 或 false；单选填选项 code（单值都放 value 字段）；多选填选项 code 列表（放 values 字段）。'
            .'对每条提取给出 0-1 的置信度。';
    }

    /**
     * 把候选属性清单、会话摘要与会话消息拼成结构化提取的用户输入。
     *
     * @param  Collection<string, AttributeDefinition>  $definitions
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function buildUserMessage(Collection $definitions, ?string $summary, array $messages): string
    {
        $definitionLines = $definitions
            ->map(function (AttributeDefinition $definition): string {
                $line = $definition->key.' | '.$definition->name.'（类型：'.$definition->type->value.'）';
                if (filled($definition->description)) {
                    $line .= ' — '.$definition->description;
                }

                if ($definition->usesOptions()) {
                    $options = collect($definition->config['options'] ?? [])
                        ->map(static fn (array $option): string => $option['code'].'='.$option['label'])
                        ->implode('，');
                    $line .= '；可选项：'.$options;
                }

                return $line;
            })
            ->values()
            ->all();

        $text = "# 候选属性清单（key | 名称）\n\n".implode("\n", $definitionLines);

        if (filled($summary)) {
            $text .= "\n\n# 会话摘要\n\n".$summary;
        }

        if ($messages !== []) {
            $messageLines = array_map(
                static fn (array $message): string => $message['role'].'：'.$message['content'],
                $messages,
            );
            $text .= "\n\n# 会话记录\n\n".implode("\n", $messageLines);
        }

        return $text;
    }
}
