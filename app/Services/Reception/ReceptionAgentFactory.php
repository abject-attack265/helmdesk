<?php

namespace App\Services\Reception;

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Services\AiRuntime\LenientHistoryTrimmer;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\History\InMemoryChatHistory;
use NeuronAI\Tools\Tool;

/**
 * 按运行时模型、系统提示词和工具组装 NeuronAI Agent。
 *
 * 聊天历史使用 LenientHistoryTrimmer，保留连续同角色消息与联系人历史 SYSTEM 消息。
 */
class ReceptionAgentFactory
{
    /**
     * 创建 NeuronAI Agent 工厂。
     */
    public function __construct(
        private readonly ReceptionProviderFactory $providers,
    ) {}

    /**
     * 组装一个 Agent。
     *
     * @param  RuntimeModelCandidateData  $modelCandidate  运行时下发的单个模型候选（含 provider 协议与凭据）
     * @param  string  $instructions  system_prompt；为空则用 NeuronAI 默认
     * @param  list<Tool>  $tools  挂载到 agent 的工具
     */
    public function make(RuntimeModelCandidateData $modelCandidate, string $instructions, array $tools = []): Agent
    {
        $agent = Agent::make()
            ->setAiProvider($this->providers->make($modelCandidate))
            ->setChatHistory(new InMemoryChatHistory(trimmer: new LenientHistoryTrimmer));

        if ($instructions !== '') {
            $agent->setInstructions($instructions);
        }

        if ($tools !== []) {
            $agent->addTool($tools);
        }

        return $agent;
    }
}
