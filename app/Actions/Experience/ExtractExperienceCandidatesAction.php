<?php

namespace App\Actions\Experience;

use App\Enums\AiCallPurpose;
use App\Enums\AiModelPurpose;
use App\Enums\ExperienceCandidateStatus;
use App\Enums\ExperienceExtractionStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\ReceptionLanguage;
use App\Models\AiModel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ExperienceExtraction;
use App\Services\Ai\NeuronStructuredGenerator;
use App\Services\Ai\Schemas\ExperienceCandidateSchema;
use App\Services\Ai\Schemas\ExperienceExtractionSchema;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\AiRuntime\AiModelPool;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * 执行一次经验提炼运行：对管理员勾选的一批联系人做 LLM 跨会话聚合，
 * 提炼出少量「主问题 + 相似问法 + 答复」候选经验落库，等待管理员采纳。
 *
 * 转录以联系人为单位：同一联系人在窗口内的会话按时间顺序拼成一段，因为访客沉默被自动关闭后隔天再来
 * 会新开一条会话，提问与人工答复常被切在两条里，只有连起来 LLM 才看得到完整上下文。
 *
 * LLM 一次看到整批联系人，同类问题在提炼阶段就聚合成一条（去重是聚合的天然副产品，不依赖检索命中）；
 * 候选不进任何索引，只作为「经验提炼」页的待处理草稿。
 */
class ExtractExperienceCandidatesAction
{
    use AsAction;

    /** 单个会话转录的字符预算。 */
    private const int MAX_CHARACTERS_PER_CONVERSATION = 6000;

    /** 单次 LLM 调用输入的转录字符预算，超出则分批提炼后合并。 */
    private const int MAX_CHARACTERS_PER_BATCH = 60000;

    /** 单次运行最多保留的候选数，按支撑会话数优先。 */
    private const int MAX_CANDIDATES_PER_RUN = 20;

    /** 单条候选保留的相似问法上限（与 QA 表单上限对齐、留润色余量）。 */
    private const int MAX_SIMILAR_QUESTIONS = 10;

    public function __construct(
        private readonly AiModelPool $aiModelPool,
        private readonly NeuronStructuredGenerator $generator,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 对运行登记的所选会话做提炼并落库候选经验，候选按 $language 书写；
     * 结束后广播应用信号让前端回源刷新。
     */
    public function handle(ExperienceExtraction $extraction, ReceptionLanguage $language): void
    {
        $conversations = $extraction->conversations()
            ->orderBy('closed_at')
            ->orderBy('id')
            ->get();

        [$transcripts, $scannedConversationIds] = $this->collectContactTranscripts($extraction, $conversations);

        if ($transcripts === []) {
            $this->complete($extraction, conversationCount: 0, candidates: []);

            return;
        }

        $models = $this->aiModelPool->modelsForPurpose(AiModelPurpose::BackgroundTask)->all();
        $batches = $this->splitIntoBatches($transcripts);

        $candidates = [];
        foreach ($batches as $batch) {
            $schema = $this->generateWithFallback($models, $extraction, $this->extractionInstructions($language), implode("\n\n", $batch));
            $candidates = [...$candidates, ...$schema->candidates];
        }

        if (count($batches) > 1 && count($candidates) > 1) {
            $candidates = $this->mergeAcrossBatches($models, $extraction, $candidates, $language);
        }

        $normalized = $this->normalizeCandidates($candidates, $scannedConversationIds);

        $this->complete($extraction, conversationCount: count($scannedConversationIds), candidates: $normalized);
    }

    /**
     * 把登记的会话按联系人分组，每个联系人拼成一段按时间顺序排列的转录。
     *
     * 整段没有任何有效人工文本的联系人跳过——没有可提炼的处理经验。
     *
     * @param  EloquentCollection<int, Conversation>  $conversations
     * @return array{0: array<string, string>, 1: list<string>} [联系人 ID => 转录, 实际送入的会话 ID 清单]
     */
    private function collectContactTranscripts(ExperienceExtraction $extraction, EloquentCollection $conversations): array
    {
        $transcripts = [];
        $scanned = [];

        $grouped = $conversations->groupBy(static fn (Conversation $conversation): string => (string) $conversation->contact_id);

        foreach ($grouped as $contactId => $contactConversations) {
            $rendered = $contactConversations
                ->map(fn (Conversation $conversation): ?array => $this->renderConversation($conversation))
                ->filter()
                ->values()
                ->all();

            $rendered = $this->trimToBatchBudget($extraction, (string) $contactId, $rendered);

            $hasTeammateText = array_any($rendered, static fn (array $item): bool => $item['has_teammate_text']);
            if (! $hasTeammateText) {
                continue;
            }

            $transcripts[(string) $contactId] = implode("\n\n", [
                '## 联系人 '.$contactId.'（同一个人，以下会话按时间先后排列）',
                ...array_map(static fn (array $item): string => $item['text'], $rendered),
            ]);

            foreach ($rendered as $item) {
                $scanned[] = $item['id'];
            }
        }

        return [$transcripts, $scanned];
    }

    /**
     * 单个会话渲染成带会话 ID 标注的转录片段，按字符预算截断；一条有效文本都没有时返回 null。
     *
     * 纯访客/AI 的会话照样渲染：它往往正是被自动关闭切走的提问，要跟着同联系人的答复一起进 LLM。
     * 这里只报告本条有没有人工文本，是否因此跳过由 collectContactTranscripts 按整个联系人判断。
     *
     * @return array{id: string, text: string, has_teammate_text: bool, length: int}|null
     */
    private function renderConversation(Conversation $conversation): ?array
    {
        $messages = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('role', [MessageRole::Visitor, MessageRole::Ai, MessageRole::Teammate])
            ->where('kind', MessageKind::Text)
            ->whereNotNull('content')
            ->whereNull('recalled_at')
            ->orderBy('seq_no')
            ->get(['role', 'content']);

        // 必须在下面的截断循环之前算：访客先刷满字符预算时人工答复会被截掉，
        // 但这条会话有人工处理过的事实不变，不该因此让整个联系人被判成没有经验可提炼。
        $hasTeammateText = $messages->contains(
            static fn (ConversationMessage $m): bool => $m->role === MessageRole::Teammate && trim((string) $m->content) !== '',
        );

        $remaining = self::MAX_CHARACTERS_PER_CONVERSATION;
        $lines = ['### 会话 ID: '.$conversation->id.'（关闭于 '.$conversation->closed_at?->toDateTimeString().'）'];

        foreach ($messages as $message) {
            $content = trim((string) $message->content);
            if ($content === '') {
                continue;
            }

            if (Str::length($content) > $remaining) {
                $content = Str::substr($content, 0, $remaining);
            }

            $lines[] = $this->roleLabel($message->role).'：'.$content;

            $remaining -= Str::length($content);
            if ($remaining <= 0) {
                break;
            }
        }

        if (count($lines) === 1) {
            return null;
        }

        $text = implode("\n", $lines);

        return [
            'id' => (string) $conversation->id,
            'text' => $text,
            'has_teammate_text' => $hasTeammateText,
            'length' => Str::length($text),
        ];
    }

    /**
     * 单个联系人的转录超出单批预算时丢掉最早的会话，保证一个联系人整段能放进一批。
     *
     * 丢最早的那些：答复通常在最近的会话里，早期会话只是上下文。
     *
     * @param  list<array{id: string, text: string, has_teammate_text: bool, length: int}>  $rendered
     * @return list<array{id: string, text: string, has_teammate_text: bool, length: int}>
     */
    private function trimToBatchBudget(ExperienceExtraction $extraction, string $contactId, array $rendered): array
    {
        $total = array_sum(array_column($rendered, 'length'));
        $dropped = 0;

        while ($total > self::MAX_CHARACTERS_PER_BATCH && count($rendered) > 1) {
            $oldest = array_shift($rendered);
            $total -= $oldest['length'];
            $dropped++;
        }

        if ($dropped > 0) {
            Log::info('[ai] 经验提炼转录超出单批预算，已丢弃该联系人较早的会话', [
                'extraction_id' => (string) $extraction->id,
                'contact_id' => $contactId,
                'dropped_conversations' => $dropped,
                'kept_conversations' => count($rendered),
            ]);
        }

        return array_values($rendered);
    }

    /**
     * 转录中展示的角色名；转录只收 Visitor / Ai / Teammate 三种文本消息。
     */
    private function roleLabel(MessageRole $role): string
    {
        return match ($role) {
            MessageRole::Visitor => '访客',
            MessageRole::Ai => 'AI',
            MessageRole::Teammate => '人工坐席',
        };
    }

    /**
     * 按单次调用字符预算把转录切成若干批；单个联系人的转录已被 trimToBatchBudget 限长，必能整段放进一批。
     *
     * @param  array<string, string>  $transcripts
     * @return list<list<string>>
     */
    private function splitIntoBatches(array $transcripts): array
    {
        $batches = [];
        $current = [];
        $currentLength = 0;

        foreach ($transcripts as $transcript) {
            $length = Str::length($transcript);
            if ($current !== [] && $currentLength + $length > self::MAX_CHARACTERS_PER_BATCH) {
                $batches[] = $current;
                $current = [];
                $currentLength = 0;
            }

            $current[] = $transcript;
            $currentLength += $length;
        }

        if ($current !== []) {
            $batches[] = $current;
        }

        return $batches;
    }

    /**
     * 依次尝试后台任务池中的模型做结构化提炼，全部失败时抛出最后一次异常。
     *
     * @param  list<AiModel>  $models
     */
    private function generateWithFallback(array $models, ExperienceExtraction $extraction, string $instructions, string $input): ExperienceExtractionSchema
    {
        if ($models === []) {
            throw new RuntimeException('No usable background task model for experience extraction.');
        }

        $lastException = null;
        foreach ($models as $model) {
            try {
                $context = AiUsageContext::forModel(
                    $model,
                    callPurpose: AiCallPurpose::ExperienceExtraction,
                );

                /** @var ExperienceExtractionSchema */
                return $this->generator->generate($model, $instructions, $input, ExperienceExtractionSchema::class, $context);
            } catch (Throwable $exception) {
                $lastException = $exception;
                Log::warning('ExtractExperienceCandidatesAction model failed.', [
                    'extraction_id' => (string) $extraction->id,
                    'ai_model_id' => (string) $model->id,
                    'reason' => $exception->getMessage(),
                ]);
            }
        }

        throw $lastException;
    }

    /**
     * 转录超过单批预算时分批提炼，再用一次 LLM 调用跨批合并语义重复的候选。
     *
     * @param  list<AiModel>  $models
     * @param  list<ExperienceCandidateSchema>  $candidates
     * @return list<ExperienceCandidateSchema>
     */
    private function mergeAcrossBatches(array $models, ExperienceExtraction $extraction, array $candidates, ReceptionLanguage $language): array
    {
        $payload = array_map(static fn (ExperienceCandidateSchema $c): array => [
            'question' => $c->question,
            'similar_questions' => $c->similar_questions,
            'answer' => $c->answer,
            'conversation_ids' => $c->conversation_ids,
        ], $candidates);

        $schema = $this->generateWithFallback(
            $models,
            $extraction,
            $this->mergeInstructions($language),
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );

        return $schema->candidates;
    }

    /**
     * 批量提炼指令：跨会话聚合、宁缺毋滥、泛化脱敏；候选按触发者界面语言书写。
     */
    private function extractionInstructions(ReceptionLanguage $language): string
    {
        $languageName = $language->label();

        return <<<PROMPT
            你是客服经验提炼助手。下面是若干位联系人的客服会话转录。
            每位联系人以「## 联系人 xxx」开头，其下是他的多段已结束会话，每段以「### 会话 ID: xxx」开头，按时间先后排列。
            你的任务：从人工坐席的处理中，归纳出可复用、可泛化的经验，整理成「主问题 + 相似问法 + 答复」的问答形式，供管理员审核后沉淀进客服知识库、由 AI 接待复用。

            阅读要求：
            - 同一位联系人下的多段会话是同一个人的连续经历，请连起来读：访客沉默一段时间后会话会被自动关闭，他再回来提问会另起一段新会话，
              所以提问经常在前一段、人工的答复在后一段。遇到「上次那个问题」这类指代，回前面的会话里找它到底指什么。
            - 不同联系人之间彼此独立，不要把一个人的上下文套到另一个人身上。

            聚合要求（最重要）：
            - 先在全部联系人之间聚类：同一类问题必须合并为一条候选，不允许输出两条本质相同的候选；conversation_ids 填上所有支撑会话的 ID（用「### 会话 ID: xxx」里的 ID，不是联系人 ID）。
            - 支撑会话越多的问题越值得沉淀，优先输出。

            取舍要求（宁缺毋滥）：
            - 只保留人工坐席真正给出了有效解决方式或明确答复口径的内容。
            - 以下内容一律不要输出：纯寒暄或事务性往来（如「稍等我查一下」）、与具体某个人强绑定的一次性处理、没有结论或未解决的会话、AI 消息里已有而人工只是重复的内容。
            - 一批会话可能产出 0 条候选；没有值得沉淀的内容时返回空列表即可，不要为了凑数硬编。
            - 最多输出 10 条。

            书写要求：
            - 泛化措辞：剔除订单号、姓名、手机号、邮箱、金额等一次性或隐私的具体值，换成占位描述。
            - 所有候选的主问题、相似问法和答复统一使用{$languageName}书写，与来源会话语言无关。
            - answer 写成可以直接回复访客的口吻，保留人工的有效话术。
            PROMPT;
    }

    /**
     * 跨批合并指令：只合并语义重复项，不改写、不增删信息；输出语言与提炼阶段一致。
     */
    private function mergeInstructions(ReceptionLanguage $language): string
    {
        $languageName = $language->label();

        return <<<PROMPT
            下面是分批提炼得到的候选经验 JSON 列表。因为分批处理，不同批次之间可能存在语义重复的候选。
            你的任务：把本质上是同一类问题的候选合并为一条——question 取更有代表性的表述，similar_questions 合并去重，answer 融合双方要点，conversation_ids 取并集。
            非重复的候选原样保留：不要改写内容，不要新增信息，也不要删除任何非重复候选。
            合并后的内容统一使用{$languageName}书写。
            PROMPT;
    }

    /**
     * 清洗候选：剔除空问题/空答案，裁剪长度与相似问法数，过滤幻觉出的会话 ID，并按支撑会话数排序截断。
     *
     * @param  list<ExperienceCandidateSchema>  $candidates
     * @param  list<string>  $scannedConversationIds
     * @return list<array{question: string, similar_questions: list<string>, answer: string, source_conversation_ids: list<string>, conversation_count: int}>
     */
    private function normalizeCandidates(array $candidates, array $scannedConversationIds): array
    {
        $scanned = array_flip($scannedConversationIds);

        $normalized = [];
        foreach ($candidates as $candidate) {
            $question = Str::limit(trim($candidate->question), 500, '');
            $answer = trim($candidate->answer);
            if ($question === '' || $answer === '') {
                continue;
            }

            $similarQuestions = [];
            foreach ($candidate->similar_questions as $similar) {
                $similar = Str::limit(trim((string) $similar), 500, '');
                if ($similar !== '' && $similar !== $question && ! in_array($similar, $similarQuestions, true)) {
                    $similarQuestions[] = $similar;
                }
            }

            $conversationIds = array_values(array_unique(array_filter(
                array_map(static fn ($id): string => trim((string) $id), $candidate->conversation_ids),
                static fn (string $id): bool => isset($scanned[$id]),
            )));

            $normalized[] = [
                'question' => $question,
                'similar_questions' => array_slice($similarQuestions, 0, self::MAX_SIMILAR_QUESTIONS),
                'answer' => $answer,
                'source_conversation_ids' => $conversationIds,
                'conversation_count' => max(1, count($conversationIds)),
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => $b['conversation_count'] <=> $a['conversation_count']);

        return array_slice($normalized, 0, self::MAX_CANDIDATES_PER_RUN);
    }

    /**
     * 落库候选并把运行标记为完成，广播应用信号。
     *
     * @param  list<array{question: string, similar_questions: list<string>, answer: string, source_conversation_ids: list<string>, conversation_count: int}>  $candidates
     */
    private function complete(ExperienceExtraction $extraction, int $conversationCount, array $candidates): void
    {
        DB::transaction(function () use ($extraction, $conversationCount, $candidates): void {
            foreach ($candidates as $candidate) {
                $extraction->candidates()->create([
                    ...$candidate,
                    'status' => ExperienceCandidateStatus::Pending,
                ]);
            }

            $extraction->update([
                'status' => ExperienceExtractionStatus::Completed,
                'conversation_count' => $conversationCount,
                'candidate_count' => count($candidates),
            ]);
        });

        Log::info('[ai] 经验提炼候选生成完成', [
            'extraction_id' => (string) $extraction->id,
            'conversation_count' => $conversationCount,
            'candidate_count' => count($candidates),
        ]);

        $this->realtimeNotifier->appChanged('experience_extraction_finished');
    }
}
