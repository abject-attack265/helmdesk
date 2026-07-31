<?php

namespace App\Actions\Conversation;

use App\Data\Conversation\GeneratedConversationTagData;
use App\Enums\AiCallPurpose;
use App\Enums\TagScope;
use App\Models\Conversation;
use App\Models\Tag;
use App\Services\Ai\NeuronStructuredGenerator;
use App\Services\Ai\Schemas\ConversationTagPickSchema;
use App\Services\Ai\Schemas\ConversationTagSelectionSchema;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\Conversation\ConversationLlmCandidateResolver;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * 受控词表下，为单次会话生成 AI 标签并落库（吃会话摘要 + 完整消息上下文）。
 * AI 只负责增补/刷新标签；删除、抑制等人工边界由 ApplyConversationTagSuggestionsAction 守住。
 */
class GenerateConversationTagsAction
{
    use AsAction;

    private const float CONFIDENCE_THRESHOLD = 0.5;

    /**
     * 注入结构化生成原语和实时通知服务。
     */
    public function __construct(
        private readonly NeuronStructuredGenerator $generator,
        private readonly ConversationLlmCandidateResolver $candidates,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 生成并应用会话标签（AI 只增补 / 刷新，不删除）。
     */
    public function handle(Conversation $conversation): void
    {
        $conversation->loadMissing('receptionPlanVersion');

        $vocabulary = $this->resolveVocabulary($conversation);
        if ($vocabulary === []) {
            return;
        }

        $messages = CollectConversationLlmContextAction::run($conversation);
        if ($messages === [] && blank($conversation->summary)) {
            return;
        }

        $candidates = array_map(static fn (Tag $tag): array => [
            'tag_id' => (string) $tag->id,
            'name' => $tag->name,
            'description' => $tag->description,
            'group' => $tag->tagGroup?->name,
        ], array_values($vocabulary));

        $suggestions = $this->generateWithCandidates($conversation, $candidates, $messages);
        $applied = $this->mapSuggestionsToTags($suggestions, $vocabulary, $conversation);

        ApplyConversationTagSuggestionsAction::run($conversation, $applied);

        Log::info('[ai] 会话标签生成完成', [
            'conversation_id' => (string) $conversation->id,
            'applied_count' => count($applied),
        ]);

        if ($applied !== []) {
            $this->realtimeNotifier->conversationChanged($conversation->refresh(), 'conversation_tags_updated');
        }
    }

    /**
     * 取当前应用会话维度的受控词表（标签 ID → Tag）。
     *
     * @return array<string, Tag>
     */
    private function resolveVocabulary(Conversation $conversation): array
    {
        $tags = Tag::query()

            ->whereHas('tagGroup', fn (Builder $query) => $query->where('scope', TagScope::Conversation))
            ->with('tagGroup')
            ->get();

        $map = [];
        foreach ($tags as $tag) {
            $map[(string) $tag->id] = $tag;
        }

        return $map;
    }

    /**
     * 把 AI 返回的标签 ID 映射回 Tag，做阈值过滤，组装成可应用的建议。
     *
     * @param  list<GeneratedConversationTagData>  $suggestions
     * @param  array<string, Tag>  $vocabulary
     * @return list<array{tag_id: string, confidence: float, reason: string|null, based_on_seq_no: int|null}>
     */
    private function mapSuggestionsToTags(array $suggestions, array $vocabulary, Conversation $conversation): array
    {
        $basedOnSeqNo = (int) $conversation->summary_last_message_seq_no ?: null;
        $applied = [];

        foreach ($suggestions as $suggestion) {
            if ($suggestion->confidence < self::CONFIDENCE_THRESHOLD) {
                continue;
            }

            if ($suggestion->tag_id === null) {
                continue;
            }

            $tag = $vocabulary[$suggestion->tag_id] ?? null;
            if ($tag === null) {
                continue;
            }

            $applied[] = [
                'tag_id' => $tag->id,
                'confidence' => $suggestion->confidence,
                'reason' => $suggestion->reason,
                'based_on_seq_no' => $basedOnSeqNo,
            ];
        }

        return $applied;
    }

    /**
     * 按候选模型顺序生成标签建议（首个成功即返回）。
     *
     * @param  list<array{tag_id: string, name: string, description: ?string, group: ?string}>  $candidates
     * @param  list<array{role: string, content: string}>  $messages
     * @return list<GeneratedConversationTagData>
     */
    private function generateWithCandidates(Conversation $conversation, array $candidates, array $messages): array
    {
        $lastError = null;

        foreach ($this->candidates->resolve($conversation) as $model) {
            try {
                $selection = $this->generator->generate(
                    $model,
                    $this->tagInstructions($conversation->visitor_locale),
                    $this->buildUserMessage($candidates, $conversation->summary, $messages),
                    ConversationTagSelectionSchema::class,
                    AiUsageContext::forModel($model, (string) $conversation->id, AiCallPurpose::ConversationTags),
                );

                return array_values(array_map(
                    static fn (ConversationTagPickSchema $pick): GeneratedConversationTagData => new GeneratedConversationTagData(
                        name: '',
                        confidence: (float) $pick->confidence,
                        reason: is_string($pick->reason) && trim($pick->reason) !== '' ? trim($pick->reason) : null,
                        tag_id: trim($pick->tag_id) !== '' ? trim($pick->tag_id) : null,
                    ),
                    $selection->tags,
                ));
            } catch (Throwable $exception) {
                $lastError = $exception;
                Log::warning('会话标签生成候选模型失败', [
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
     * 打标签指令：约束只能从受控词表中选 tag_id，并按语言输出理由。
     */
    private function tagInstructions(string $locale): string
    {
        return '你是客服会话打标签助手。只能从给定候选词表中选择适用的标签，tag_id 必须严格来自候选项；'
            .'不要创造词表外的标签。对每个选中的标签给出 0-1 的置信度和简短理由（用'.$locale.'语言）。'
            .'如果没有任何标签适用，返回空列表。';
    }

    /**
     * 把候选词表、既有摘要与会话消息拼成结构化抽取的用户输入。
     *
     * @param  list<array{tag_id: string, name: string, description: ?string, group: ?string}>  $candidates
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function buildUserMessage(array $candidates, ?string $summary, array $messages): string
    {
        $vocabularyLines = array_map(static function (array $candidate): string {
            $parts = $candidate['tag_id'].' | '.$candidate['name'];
            if (filled($candidate['group'])) {
                $parts .= '（分组：'.$candidate['group'].'）';
            }
            if (filled($candidate['description'])) {
                $parts .= ' — '.$candidate['description'];
            }

            return $parts;
        }, $candidates);

        $text = "# 候选标签词表（tag_id | 名称）\n\n".implode("\n", $vocabularyLines);

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
