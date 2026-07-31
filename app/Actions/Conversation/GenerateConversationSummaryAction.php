<?php

namespace App\Actions\Conversation;

use App\Data\Conversation\GeneratedConversationSummaryData;
use App\Enums\AiCallPurpose;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Jobs\Contact\GenerateContactHandoffBriefJob;
use App\Jobs\Conversation\GenerateContactAttributeValuesJob;
use App\Jobs\Conversation\GenerateConversationTagsJob;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Ai\NeuronStructuredGenerator;
use App\Services\Ai\Schemas\ConversationSummarySchema;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\Conversation\ConversationLlmCandidateResolver;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;
use UnexpectedValueException;

/**
 * 为单次会话生成并保存访客语言摘要。
 */
class GenerateConversationSummaryAction
{
    use AsAction;

    /**
     * 注入结构化生成原语、候选模型解析与实时通知服务。
     */
    public function __construct(
        private readonly NeuronStructuredGenerator $generator,
        private readonly ConversationLlmCandidateResolver $candidates,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 根据完整会话文本生成摘要，并同步更新联系人接手简报。
     */
    public function handle(Conversation $conversation, bool $force = false): ?string
    {
        $conversation->loadMissing(['receptionPlanVersion', 'contact']);
        $latestSeqNo = $this->latestTextSeqNo($conversation);

        if ($latestSeqNo === null) {
            return null;
        }

        if (! $force && filled($conversation->summary) && (int) $conversation->summary_last_message_seq_no >= $latestSeqNo) {
            return $conversation->summary;
        }

        $messages = CollectConversationLlmContextAction::run($conversation);
        if ($messages === []) {
            return $conversation->summary;
        }

        $result = $this->generateWithCandidates($conversation, $messages);
        $summary = $this->normalizeSummary($result->summary);

        $conversation->forceFill([
            'summary' => $summary,
            'summary_locale' => $conversation->visitor_locale,
            'summary_translations' => null,
            'summary_last_message_seq_no' => $latestSeqNo,
            'summary_generated_at' => now(),
        ])->save();

        Log::info('[ai] 会话摘要生成完成', [
            'conversation_id' => (string) $conversation->id,
        ]);

        $this->realtimeNotifier->conversationChanged($conversation->refresh(), 'conversation_summary_updated');

        // 摘要完成后再基于同一段完整上下文打标签；AI 标签只增补/刷新，不负责删除。
        Log::info('[conversation-tags] dispatching tags job after summary', [
            'conversation_id' => $conversation->id,
        ]);
        GenerateConversationTagsJob::dispatch((string) $conversation->id)->afterCommit();

        if ($conversation->contact_id !== null) {
            GenerateContactHandoffBriefJob::dispatch(
                (string) $conversation->contact_id,
                (string) $conversation->id,
            )->afterCommit();

            // 基于同一段完整上下文提取联系人 AI 可写属性；只补充/刷新 AI 来源的值，不覆盖人工数据。
            GenerateContactAttributeValuesJob::dispatch((string) $conversation->id)->afterCommit();
        }

        return $summary;
    }

    /**
     * 获取当前会话最后一条可进入摘要的文本消息 seq_no。
     */
    private function latestTextSeqNo(Conversation $conversation): ?int
    {
        $seqNo = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('role', [MessageRole::Visitor, MessageRole::Ai, MessageRole::Teammate])
            ->where('kind', MessageKind::Text)
            ->whereNotNull('content')
            ->whereNull('recalled_at')
            ->max('seq_no');

        return $seqNo !== null ? (int) $seqNo : null;
    }

    /**
     * 按接待方案候选模型顺序生成摘要。
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function generateWithCandidates(Conversation $conversation, array $messages): GeneratedConversationSummaryData
    {
        $lastError = null;

        foreach ($this->candidates->resolve($conversation) as $model) {
            try {
                $schema = $this->generator->generate(
                    $model,
                    $this->summaryInstructions($conversation->visitor_locale),
                    $this->buildUserMessage($messages, $conversation->summary),
                    ConversationSummarySchema::class,
                    AiUsageContext::forModel($model, (string) $conversation->id, AiCallPurpose::ConversationSummary),
                );

                return new GeneratedConversationSummaryData(
                    summary: trim($schema->summary),
                );
            } catch (Throwable $exception) {
                $lastError = $exception;
                Log::warning('会话摘要生成候选模型失败', [
                    'conversation_id' => $conversation->id,
                    'ai_model_id' => $model->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        throw $lastError ?? new \RuntimeException('No available conversation summary model.');
    }

    /**
     * 返回约束语言和客观性的摘要生成指令。
     */
    private function summaryInstructions(string $locale): string
    {
        return '你是客服会话摘要助手。请基于完整会话，用'.$locale.'语言写出客观、信息完整的摘要，'
            .'覆盖访客诉求与处理进展，不要编造未出现的信息。';
    }

    /**
     * 把会话消息与既有摘要拼成结构化抽取的用户输入。
     *
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function buildUserMessage(array $messages, ?string $existingSummary): string
    {
        $lines = array_map(
            static fn (array $message): string => $message['role'].'：'.$message['content'],
            $messages,
        );

        $text = "# 会话记录\n\n".implode("\n", $lines);
        if (filled($existingSummary)) {
            $text .= "\n\n# 既有摘要（可参考并更新）\n\n".$existingSummary;
        }

        return $text;
    }

    /**
     * 清理摘要文本。
     */
    private function normalizeSummary(string $summary): string
    {
        if (! mb_check_encoding($summary, 'UTF-8')) {
            throw new UnexpectedValueException('会话摘要必须是 UTF-8 文本。');
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($summary));
        if (! is_string($normalized) || $normalized === '') {
            throw new UnexpectedValueException('会话摘要不能为空。');
        }

        return Str::limit($normalized, 1200, '');
    }
}
