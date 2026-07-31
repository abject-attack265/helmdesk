<?php

namespace App\Services\Reception;

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Data\Reception\Runtime\ReceptionToolEventContextData;
use App\Services\Ai\ModelSystemContext;
use App\Services\Ai\Usage\AiUsageContext;
use Illuminate\Support\Facades\Log;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Tools\Tool;
use Throwable;

/**
 * 使用单个候选模型执行一轮 AI 接待流式推理。
 *
 * 当前会话使用 user / assistant 消息序列。联系人历史在 OpenAI 协议中使用独立
 * SYSTEM 消息，在 Anthropic 和 Gemini 协议中并入系统提示词。
 */
class ReceptionTurnExecutor
{
    /**
     * 单个候选模型的流式推理时限，为模型切换和回复投递保留作业时间。
     */
    private const float ATTEMPT_TIMEOUT_SECONDS = 60.0;

    /**
     * 创建接待推理执行器。
     */
    public function __construct(
        private readonly ReceptionAgentFactory $agents,
        private readonly ReceptionTurnRunner $runner,
        private readonly ModelSystemContext $systemContext,
    ) {}

    /**
     * 用指定候选模型跑一次推理，返回流式消费结果。
     *
     * @param  list<Tool>  $tools  挂载到 agent 的工具
     * @param  list<Message>  $conversationHistory  当前会话本轮之前的标准 user / assistant 消息
     * @param  string  $contactHistoryContext  同一联系人其他会话的背景文本；空串表示无历史会话
     * @param  UserMessage  $newMessage  本轮按候选模型能力构建的访客消息
     * @param  AiUsageContext  $usageContext  本轮 token 用量归因（应用 / 会话 / 模型）
     * @param  ReceptionToolEventContextData|null  $toolEventContext  工具事件记录与异常处理上下文
     */
    public function execute(
        RuntimeModelCandidateData $modelCandidate,
        string $systemPrompt,
        array $tools,
        array $conversationHistory,
        string $contactHistoryContext,
        UserMessage $newMessage,
        string $conversationId,
        string $turnId,
        AiUsageContext $usageContext,
        ?ReceptionToolEventContextData $toolEventContext = null,
    ): ReceptionTurnOutcome {
        $agent = $this->agents->make(
            $modelCandidate,
            $this->systemContext->instructions($modelCandidate, $systemPrompt, $contactHistoryContext),
            $tools,
        );
        $agent->toolErrorHandler(new ReceptionToolErrorHandler($toolEventContext));
        $usageContext->attachObservers($agent);
        if ($toolEventContext !== null) {
            $agent->observe(new ReceptionToolEventObserver($toolEventContext));
        }

        $contextMessage = $this->systemContext->historyMessage($modelCandidate, $contactHistoryContext);
        if ($contextMessage !== null) {
            $agent->getChatHistory()->addMessage($contextMessage);
        }

        foreach ($conversationHistory as $message) {
            $agent->getChatHistory()->addMessage($message);
        }

        try {
            $handler = $agent->stream($newMessage);

            return $this->runner->consume($handler->events(), $conversationId, $turnId, self::ATTEMPT_TIMEOUT_SECONDS);
        } catch (Throwable $e) {
            Log::warning('[reception] 接待推理候选模型调用失败', [
                'conversation_id' => $conversationId,
                'turn_id' => $turnId,
                'provider' => $modelCandidate->provider_name,
                'brand' => $modelCandidate->brand,
                'protocol' => $modelCandidate->protocol->value,
                'model' => $modelCandidate->model_name,
                'model_id' => $modelCandidate->model_id,
                'ai_model_id' => $modelCandidate->ai_model_id,
                'has_media' => $this->hasMediaBlocks($newMessage),
                'error_class' => $e::class,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 判断本轮新消息是否携带非文本内容块。
     */
    private function hasMediaBlocks(UserMessage $message): bool
    {
        foreach ($message->getContentBlocks() as $block) {
            if (! $block instanceof TextContent) {
                return true;
            }
        }

        return false;
    }
}
