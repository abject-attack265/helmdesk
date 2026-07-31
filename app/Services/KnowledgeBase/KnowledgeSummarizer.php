<?php

namespace App\Services\KnowledgeBase;

use App\Models\AiModel;
use App\Services\Reception\ReceptionProviderFactory;
use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\UserMessage;

/**
 * RAPTOR 摘要生成。
 *
 * 对每个聚类批次（一组子节点文本）调一次 LLM，生成一段摘要，构成 RAPTOR 摘要树的上层节点。
 * provider 复用 ReceptionProviderFactory 的 protocol→NeuronAI provider 映射，逐 batch 用独立 agent 调用
 * （摘要之间相互独立，不共享对话历史）。
 */
class KnowledgeSummarizer
{
    public function __construct(
        private readonly ReceptionProviderFactory $providers,
    ) {}

    /**
     * 为每个批次生成摘要，返回与批次一一对应的摘要列表。
     *
     * @param  list<list<string>>  $batches  每个批次是一组待摘要的子节点文本
     * @return list<string>
     */
    public function summarizeBatches(AiModel $model, array $batches): array
    {
        if ($batches === []) {
            return [];
        }

        $summaries = [];
        foreach ($batches as $batch) {
            $text = implode("\n\n", array_map(static fn ($t): string => (string) $t, $batch));

            $agent = Agent::make()
                ->setAiProvider($this->providers->makeForModel($model))
                ->setInstructions('你是知识库摘要助手。把下面的内容概括成一段简洁、信息密度高的摘要，保留关键事实，不要编造；使用与原文相同的语言。');

            $message = $agent->chat(new UserMessage("# 待摘要内容\n\n".$text))->getMessage();
            $summaries[] = trim((string) $message->getContent());
        }

        return $summaries;
    }
}
