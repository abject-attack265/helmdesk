<?php

namespace App\Jobs\AiChat;

use App\Actions\AiChat\FinalizeAiAssistantMessageAction;
use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Data\Integration\IntegrationToolSourceRuntimeData;
use App\Enums\AiAssistantMessageStatus;
use App\Services\AiChat\AiChatStreamRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 后台执行一轮侧边栏 AI 助手对话的流式推理。
 */
class RunAiChatStreamJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    /**
     * 保存本轮流式推理所需的模型候选、消息和应用上下文。
     *
     * @param  list<RuntimeModelCandidateData>  $modelCandidates
     * @param  list<array{role: string, content: string, attachment_ids?: list<string>}>  $messages
     * @param  list<array{id: string, name: string, description: string}>  $knowledgeBases
     * @param  list<IntegrationToolSourceRuntimeData>  $integrationToolSources
     */
    public function __construct(
        public readonly string $roundId,
        public readonly array $modelCandidates,
        public readonly array $messages,
        public readonly array $knowledgeBases,
        public readonly array $integrationToolSources,
        public readonly string $systemName,
        public readonly string $userTimezone,
        public readonly string $conversationId,
        public readonly string $threadId,
        public readonly string $assistantMessageId,
    ) {
        $this->queue = 'interactive-ai';
    }

    /**
     * 执行流式推理并持久化最终回答。
     */
    public function handle(AiChatStreamRunner $runner): void
    {
        $runner->run(
            $this->roundId,
            $this->modelCandidates,
            $this->messages,
            $this->knowledgeBases,
            $this->integrationToolSources,
            $this->systemName,
            $this->userTimezone,
            $this->conversationId,
            $this->threadId,
            $this->assistantMessageId,
        );
    }

    /**
     * 队列超时或基础设施异常时把回答从 pending 收口为 failed。
     */
    public function failed(Throwable $exception): void
    {
        app(FinalizeAiAssistantMessageAction::class)->handle(
            $this->assistantMessageId,
            AiAssistantMessageStatus::Failed,
        );

        Log::warning('AI 助手流式任务执行失败。', [
            'conversation_id' => $this->conversationId,
            'thread_id' => $this->threadId,
            'round_id' => $this->roundId,
            'assistant_message_id' => $this->assistantMessageId,
            'candidate_count' => count($this->modelCandidates),
            'candidate_ai_model_ids' => array_map(
                static fn (RuntimeModelCandidateData $candidate): string => $candidate->ai_model_id,
                $this->modelCandidates,
            ),
            'exception_class' => $exception::class,
            'reason' => $exception->getMessage(),
        ]);
    }
}
