<?php

namespace App\Services\AiChat;

use App\Actions\AiChat\BuildAiAssistantConversationContextAction;
use App\Actions\AiChat\FinalizeAiAssistantMessageAction;
use App\Actions\AiChat\ListAiAssistantHistoryThreadsAction;
use App\Data\AiChat\AiAssistantConversationContextData;
use App\Data\AiChat\AiAssistantHistoryCatalogData;
use App\Data\AiChat\AiChatStreamAttemptResultData;
use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Data\Integration\IntegrationToolSourceRuntimeData;
use App\Enums\AiAssistantMessageStatus;
use App\Enums\AiCallPurpose;
use App\Enums\AiModelPurpose;
use App\Events\AiChat\AiChatStreamChunk;
use App\Exceptions\AiChatStreamStartedException;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Services\Ai\ModelSystemContext;
use App\Services\Ai\MultimodalMessageBuilder;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\AiRuntime\AiModelFallback;
use App\Services\AiRuntime\MediaAwareModelCandidatePrioritizer;
use App\Services\Integration\IntegrationToolBuilder;
use App\Services\KnowledgeBase\KnowledgeSearchToolFactory;
use App\Services\Reception\ReceptionAgentFactory;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use LogicException;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Tools\Tool;
use Throwable;

/**
 * 运行一轮侧边栏 AI 助手对话，并把流式结果广播到该轮 Mercure 主题。
 */
class AiChatStreamRunner
{
    /**
     * 注入流式对话需要的模型、工具、持久化和消息服务。
     */
    public function __construct(
        private readonly ReceptionAgentFactory $agents,
        private readonly CalculatorToolFactory $calculatorTools,
        private readonly KnowledgeSearchToolFactory $knowledgeSearchTools,
        private readonly AiChatStreamSignal $signal,
        private readonly IntegrationToolBuilder $integrationToolBuilder,
        private readonly MultimodalMessageBuilder $messageBuilder,
        private readonly FinalizeAiAssistantMessageAction $finalizeMessage,
        private readonly AiAssistantHistoryToolFactory $historyToolFactory,
        private readonly ListAiAssistantHistoryThreadsAction $listHistoryThreads,
        private readonly AiChatToolStreamPayloadBuilder $toolPayloads,
        private readonly BuildAiAssistantConversationContextAction $buildConversationContext,
        private readonly ModelSystemContext $systemContext,
        private readonly AiModelFallback $modelFallback,
        private readonly MediaAwareModelCandidatePrioritizer $candidatePrioritizer,
    ) {}

    /**
     * 跑一轮流式对话并逐块广播到该轮对话主题。
     *
     * @param  list<RuntimeModelCandidateData>  $modelCandidates
     * @param  list<array{role: string, content: string, attachment_ids?: list<string>}>  $messages
     * @param  list<array{id: string, name: string, description: string}>  $knowledgeBases
     * @param  list<IntegrationToolSourceRuntimeData>  $integrationToolSources
     */
    public function run(
        string $roundId,
        array $modelCandidates,
        array $messages,
        array $knowledgeBases,
        array $integrationToolSources,
        string $systemName,
        string $userTimezone,
        string $conversationId,
        string $threadId,
        string $assistantMessageId,
    ): void {
        $assistantContent = '';
        $assistantSegments = [];
        $toolCallCount = 0;
        $logContext = [
            'conversation_id' => $conversationId,
            'thread_id' => $threadId,
            'round_id' => $roundId,
            'assistant_message_id' => $assistantMessageId,
            'candidate_count' => count($modelCandidates),
        ];

        try {
            $timezone = new DateTimeZone($userTimezone);
            $conversation = Conversation::query()->findOrFail($conversationId);
            $historyCatalog = $this->listHistoryThreads->handle(
                $conversation,
                $threadId,
                $timezone,
            );
            $conversationContext = $this->buildConversationContext->handle($conversation);
            $systemPrompt = $this->systemPrompt($systemName, $timezone, $historyCatalog);
            [$requiresImageInput, $requiresVideoInput] = $this->requiredMediaInputs($messages);
            $orderedCandidates = $this->candidatePrioritizer->prioritize(
                $modelCandidates,
                $requiresImageInput,
                $requiresVideoInput,
            );
            $this->logCandidatePriorityChange(
                $modelCandidates,
                $orderedCandidates,
                $requiresImageInput,
                $requiresVideoInput,
                $logContext,
            );
            Log::info('AI 助手流式对话开始。', [
                ...$logContext,
                'requires_image_input' => $requiresImageInput,
                'requires_video_input' => $requiresVideoInput,
                'conversation_context_length' => mb_strlen($conversationContext->context),
                'candidate_ai_model_ids' => array_map(
                    static fn (RuntimeModelCandidateData $candidate): string => $candidate->ai_model_id,
                    $orderedCandidates,
                ),
            ]);

            /** @var AiChatStreamAttemptResultData $result */
            $result = $this->modelFallback->run(
                $orderedCandidates,
                fn (RuntimeModelCandidateData $candidate, int $attemptIndex): AiChatStreamAttemptResultData => $this->runAttempt(
                    modelCandidate: $candidate,
                    attemptIndex: $attemptIndex,
                    roundId: $roundId,
                    messages: $messages,
                    knowledgeBases: $knowledgeBases,
                    integrationToolSources: $integrationToolSources,
                    systemPrompt: $systemPrompt,
                    conversationContext: $conversationContext,
                    timezone: $timezone,
                    conversationId: $conversationId,
                    threadId: $threadId,
                ),
            );
            $assistantContent = $result->content;
            $assistantSegments = $result->segments;
            $toolCallCount = $result->tool_call_count;

            $this->finalizeMessage->handle(
                $assistantMessageId,
                AiAssistantMessageStatus::Completed,
                $assistantSegments,
            );

            $resultContext = [
                ...$logContext,
                'ai_model_id' => $result->model_candidate->ai_model_id,
                'model_id' => $result->model_candidate->model_id,
                'provider_name' => $result->model_candidate->provider_name,
                'output_length' => mb_strlen($assistantContent),
                'segment_count' => count($assistantSegments),
                'tool_call_count' => $toolCallCount,
            ];
            if ($result->cancelled) {
                Log::info('AI 助手流式对话已由客服停止。', $resultContext);
            } elseif ($assistantContent === '') {
                Log::warning('AI 助手流式对话未产出文本。', $resultContext);
            } else {
                Log::info('AI 助手流式对话完成。', $resultContext);
            }

            AiChatStreamChunk::dispatch($roundId, ['type' => 'done']);
        } catch (Throwable $exception) {
            if ($exception instanceof AiChatStreamStartedException) {
                $assistantContent = $exception->content;
                $assistantSegments = $exception->segments;
                $toolCallCount = $exception->tool_call_count;
            }
            $rootException = $exception->getPrevious();

            $this->finalizeMessage->handle(
                $assistantMessageId,
                AiAssistantMessageStatus::Failed,
                $assistantSegments,
            );
            Log::warning('AI 助手流式对话失败。', [
                ...$logContext,
                'exception_class' => $exception::class,
                'root_exception_class' => $rootException !== null ? $rootException::class : null,
                'output_length' => mb_strlen($assistantContent),
                'reason' => $exception->getMessage(),
                'root_reason' => $rootException?->getMessage(),
                'segment_count' => count($assistantSegments),
                'tool_call_count' => $toolCallCount,
            ]);

            AiChatStreamChunk::dispatch($roundId, ['type' => 'error', 'error' => '生成回复时发生错误。']);
        } finally {
            $this->signal->clear($roundId);
        }
    }

    /**
     * 使用单个候选执行流式生成，并保留已广播片段的失败现场。
     *
     * @param  list<array{role: string, content: string, attachment_ids?: list<string>}>  $messages
     * @param  list<array{id: string, name: string, description: string}>  $knowledgeBases
     * @param  list<IntegrationToolSourceRuntimeData>  $integrationToolSources
     */
    private function runAttempt(
        RuntimeModelCandidateData $modelCandidate,
        int $attemptIndex,
        string $roundId,
        array $messages,
        array $knowledgeBases,
        array $integrationToolSources,
        string $systemPrompt,
        AiAssistantConversationContextData $conversationContext,
        DateTimeZone $timezone,
        string $conversationId,
        string $threadId,
    ): AiChatStreamAttemptResultData {
        $assistantContent = '';
        $assistantSegments = [];
        $toolCallCount = 0;
        $cancelled = false;
        $hasBroadcastChunk = false;

        try {
            [$tools, $toolDisplayNames] = $this->buildTools(
                $conversationId,
                $threadId,
                $knowledgeBases,
                $integrationToolSources,
                $timezone,
            );
            $agent = $this->agents->make(
                $modelCandidate,
                $this->systemContext->instructions(
                    $modelCandidate,
                    $systemPrompt,
                    $conversationContext->context,
                ),
                $tools,
            );
            AiUsageContext::forCandidate(
                $modelCandidate,
                AiModelPurpose::Assistant,
                conversationId: $conversationId,
                callPurpose: AiCallPurpose::Assistant,
            )->attachObservers($agent);

            $contextMessage = $this->systemContext->historyMessage(
                $modelCandidate,
                $conversationContext->context,
            );
            if ($contextMessage !== null) {
                $agent->getChatHistory()->addMessage($contextMessage);
            }

            [$history, $userMessage] = $this->splitMessages(
                $messages,
                $modelCandidate,
            );
            foreach ($history as $message) {
                $agent->getChatHistory()->addMessage($message);
            }

            foreach ($agent->stream($userMessage)->events() as $chunk) {
                if ($this->signal->isCancelled($roundId)) {
                    $cancelled = true;

                    break;
                }

                if ($chunk instanceof TextChunk) {
                    $content = (string) $chunk->content;
                    if ($content !== '') {
                        $assistantContent .= $content;
                        $this->appendTextSegment($assistantSegments, $content);
                        AiChatStreamChunk::dispatch($roundId, ['type' => 'delta', 'content' => $content]);
                        $hasBroadcastChunk = true;
                    }

                    continue;
                }

                if ($chunk instanceof ToolCallChunk) {
                    $toolCallCount++;
                    $payload = $this->toolPayloads->call($chunk->tool, $toolDisplayNames);
                    $assistantSegments[] = $payload;
                    AiChatStreamChunk::dispatch($roundId, $payload);
                    $hasBroadcastChunk = true;

                    continue;
                }

                if ($chunk instanceof ToolResultChunk) {
                    $payload = $this->toolPayloads->result($chunk->tool, $toolDisplayNames);
                    $assistantSegments[] = $payload;
                    AiChatStreamChunk::dispatch($roundId, $payload);
                    $hasBroadcastChunk = true;
                }
            }
        } catch (Throwable $exception) {
            if ($hasBroadcastChunk) {
                Log::warning('AI 助手流式输出期间模型调用失败。', [
                    'conversation_id' => $conversationId,
                    'round_id' => $roundId,
                    'attempt_index' => $attemptIndex,
                    'ai_model_id' => $modelCandidate->ai_model_id,
                    'model_id' => $modelCandidate->model_id,
                    'provider_name' => $modelCandidate->provider_name,
                    'exception_class' => $exception::class,
                    'reason' => $exception->getMessage(),
                ]);

                throw new AiChatStreamStartedException(
                    segments: $assistantSegments,
                    content: $assistantContent,
                    tool_call_count: $toolCallCount,
                    previous: $exception,
                );
            }

            Log::warning('AI 助手模型候选调用失败。', [
                'conversation_id' => $conversationId,
                'round_id' => $roundId,
                'attempt_index' => $attemptIndex,
                'ai_model_id' => $modelCandidate->ai_model_id,
                'model_id' => $modelCandidate->model_id,
                'provider_name' => $modelCandidate->provider_name,
                'exception_class' => $exception::class,
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return new AiChatStreamAttemptResultData(
            model_candidate: $modelCandidate,
            content: $assistantContent,
            segments: $assistantSegments,
            tool_call_count: $toolCallCount,
            cancelled: $cancelled,
        );
    }

    /**
     * 判断客服在助手中主动上传的附件需要的模型输入能力。
     *
     * @param  list<array{role: string, content: string, attachment_ids?: list<string>}>  $messages
     * @return array{0: bool, 1: bool}
     */
    private function requiredMediaInputs(array $messages): array
    {
        $attachmentIds = [];
        foreach ($messages as $message) {
            $attachmentIds = [...$attachmentIds, ...($message['attachment_ids'] ?? [])];
        }
        $attachmentIds = array_values(array_unique($attachmentIds));
        if ($attachmentIds === []) {
            return [false, false];
        }

        $attachments = Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->get(['mime_type']);

        return [
            $attachments->contains(
                static fn (Attachment $attachment): bool => str_starts_with((string) $attachment->mime_type, 'image/'),
            ),
            $attachments->contains(
                static fn (Attachment $attachment): bool => str_starts_with((string) $attachment->mime_type, 'video/'),
            ),
        ];
    }

    /**
     * 在媒体能力改变候选顺序时记录实际选择依据。
     *
     * @param  list<RuntimeModelCandidateData>  $original
     * @param  list<RuntimeModelCandidateData>  $prioritized
     * @param  array<string, mixed>  $logContext
     */
    private function logCandidatePriorityChange(
        array $original,
        array $prioritized,
        bool $requiresImageInput,
        bool $requiresVideoInput,
        array $logContext,
    ): void {
        $originalIds = array_map(
            static fn (RuntimeModelCandidateData $candidate): string => $candidate->ai_model_id,
            $original,
        );
        $prioritizedIds = array_map(
            static fn (RuntimeModelCandidateData $candidate): string => $candidate->ai_model_id,
            $prioritized,
        );

        if ($originalIds === $prioritizedIds) {
            return;
        }

        Log::info('AI 助手按媒体输入能力调整模型候选顺序。', [
            ...$logContext,
            'requires_image_input' => $requiresImageInput,
            'requires_video_input' => $requiresVideoInput,
            'original_ai_model_ids' => $originalIds,
            'prioritized_ai_model_ids' => $prioritizedIds,
        ]);
    }

    /**
     * 合并相邻文本增量，保留文本与工具事件的发生顺序。
     *
     * @param  list<array<string, mixed>>  $segments
     */
    private function appendTextSegment(array &$segments, string $content): void
    {
        $lastIndex = count($segments) - 1;
        if ($lastIndex >= 0 && ($segments[$lastIndex]['type'] ?? null) === 'text') {
            $segments[$lastIndex]['content'] .= $content;

            return;
        }

        $segments[] = [
            'type' => 'text',
            'content' => $content,
        ];
    }

    /**
     * 根据系统名称、客服时区和历史线程目录构建提示词。
     */
    private function systemPrompt(
        string $systemName,
        DateTimeZone $timezone,
        AiAssistantHistoryCatalogData $historyCatalog,
    ): string {
        $name = trim($systemName);
        $currentDateTime = Carbon::now($timezone)->format('Y-m-d H:i:s');
        $catalog = json_encode(
            $historyCatalog->toArray(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return "你是 {$name} 工作台的 AI 助手，服务于客服与运营人员。"
            ."当前日期与时间：{$currentDateTime}（{$timezone->getName()}）。"
            .'回答应简洁、准确、可执行；不确定时如实说明，使用与用户相同的语言。'
            ."历史 AI 对话目录：{$catalog}。需要时使用 ai_assistant_history 查证。"
            .'工具、检索过程和内部标识只用于推理，不向用户提及，直接自然地回答结论。';
    }

    /**
     * 按当前模型能力构建历史消息与本轮用户消息。
     *
     * @param  list<array{role: string, content: string, attachment_ids?: list<string>}>  $messages
     * @return array{0: list<Message>, 1: Message}
     */
    private function splitMessages(
        array $messages,
        RuntimeModelCandidateData $modelCandidate,
    ): array {
        $last = array_last($messages);
        if ($last === null || $last['role'] !== 'user') {
            throw new LogicException('AI 助手消息序列必须以用户消息结束。');
        }
        array_pop($messages);
        $userMessage = $this->buildMessage('user', $last, $modelCandidate);

        $history = [];
        foreach ($messages as $message) {
            $role = $message['role'];
            if (! in_array($role, ['user', 'assistant'], true)) {
                throw new LogicException('AI 助手历史消息角色必须是 user 或 assistant。');
            }
            $history[] = $this->buildMessage(
                $role,
                $message,
                $modelCandidate,
            );
        }

        return [$history, $userMessage];
    }

    /**
     * 把文本与图片/视频附件构建成 NeuronAI 消息。
     *
     * 媒体内容块只挂在 user 消息上。
     *
     * @param  array{content: string, attachment_ids?: list<string>}  $message
     */
    private function buildMessage(
        string $role,
        array $message,
        RuntimeModelCandidateData $modelCandidate,
    ): Message {
        $blocks = [];

        $content = trim($message['content']);
        if ($content !== '') {
            $blocks[] = new TextContent($content);
        }

        if ($role === 'user') {
            $attachments = $this->resolveAttachments($message['attachment_ids'] ?? []);
            foreach ($this->messageBuilder->attachmentBlocks(
                $attachments,
                $modelCandidate->supports_image_input,
                $modelCandidate->supports_video_input,
                $modelCandidate->ai_model_id,
            ) as $block) {
                $blocks[] = $block;
            }
        }

        if ($blocks === []) {
            throw new LogicException('AI 助手消息必须包含文本或可用的媒体附件。');
        }

        return match ($role) {
            'assistant' => new AssistantMessage($blocks),
            'user' => new UserMessage($blocks),
            default => throw new LogicException('AI 助手消息角色无效。'),
        };
    }

    /**
     * 按 ID 和输入顺序解析附件，并记录不可用附件。
     *
     * @param  list<string>  $attachmentIds
     * @return Collection<int, Attachment>
     */
    private function resolveAttachments(array $attachmentIds): Collection
    {
        if ($attachmentIds === []) {
            return collect();
        }

        $byId = Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->with('storageProfile')
            ->get()
            ->keyBy(fn (Attachment $attachment): string => (string) $attachment->id);
        $unavailableIds = array_values(array_filter(
            array_unique($attachmentIds),
            static fn (string $id): bool => ! $byId->has($id),
        ));

        if ($unavailableIds !== []) {
            Log::warning('AI 助手消息引用的附件不可用。', [
                'attachment_ids' => $unavailableIds,
            ]);
        }

        return collect($attachmentIds)
            ->map(fn (string $id): ?Attachment => $byId->get($id))
            ->filter(static fn (?Attachment $attachment): bool => $attachment !== null)
            ->values();
    }

    /**
     * 组装本轮可用工具，并为集成工具建立人类可读名称。
     *
     * @param  list<array{id: string, name: string, description: string}>  $knowledgeBases
     * @param  list<IntegrationToolSourceRuntimeData>  $integrationToolSources
     * @return array{0: list<Tool>, 1: array<string, string>}
     */
    private function buildTools(
        string $conversationId,
        string $threadId,
        array $knowledgeBases,
        array $integrationToolSources,
        DateTimeZone $timezone,
    ): array {
        $tools = [
            $this->calculatorTools->build(),
            $this->historyToolFactory->build($conversationId, $threadId, $timezone),
        ];
        $toolDisplayNames = [];

        if ($knowledgeBases !== []) {
            $knowledgeBaseIds = array_map(
                static fn (array $knowledgeBase): string => $knowledgeBase['id'],
                $knowledgeBases,
            );
            $tools[] = $this->knowledgeSearchTools->buildKnowledgeSearchTool($knowledgeBaseIds);
        }

        foreach ($integrationToolSources as $source) {
            foreach ($this->integrationToolBuilder->build([$source]) as $tool) {
                $tools[] = $tool;
                $toolDisplayNames[$tool->getName()] = "{$source->name} / {$tool->getName()}";
            }
        }

        return [$tools, $toolDisplayNames];
    }
}
