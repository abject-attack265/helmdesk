<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\Reception\ReceptionProviderFactory;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Tools\Tool;

/**
 * 执行带用量与调用日志观测的 LLM 文本生成。
 */
class NeuronChatGenerator
{
    public function __construct(
        private readonly ReceptionProviderFactory $providers,
    ) {}

    /**
     * 用指定模型按 instructions 对 userMessage 生成一段文本；$context 用于把本次调用的 token 用量与调用日志归因落库。
     *
     * @param  list<Tool>  $tools  可选工具集；非空时由模型在 ReAct 循环中自行调用
     */
    public function generate(AiModel $model, string $instructions, string $userMessage, AiUsageContext $context, array $tools = []): string
    {
        $agent = Agent::make()->setAiProvider($this->providers->makeForModel($model));
        $context->attachObservers($agent);
        if (trim($instructions) !== '') {
            $agent->setInstructions($instructions);
        }
        if ($tools !== []) {
            $agent->addTool($tools);
        }

        $message = $agent->chat(new UserMessage($userMessage))->getMessage();

        return trim((string) $message->getContent());
    }
}
