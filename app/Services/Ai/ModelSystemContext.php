<?php

namespace App\Services\Ai;

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Enums\AiProviderProtocol;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;

/**
 * 按模型协议将补充系统背景放入系统提示词或独立 SYSTEM 消息。
 */
class ModelSystemContext
{
    /**
     * 返回创建 Agent 时使用的系统提示词。
     */
    public function instructions(
        RuntimeModelCandidateData $modelCandidate,
        string $systemPrompt,
        string $context,
    ): string {
        if ($context === '' || $modelCandidate->protocol === AiProviderProtocol::OpenAI) {
            return $systemPrompt;
        }

        return $systemPrompt."\n\n".$context;
    }

    /**
     * OpenAI 协议使用独立 SYSTEM 消息承载补充背景。
     */
    public function historyMessage(
        RuntimeModelCandidateData $modelCandidate,
        string $context,
    ): ?Message {
        if ($context === '' || $modelCandidate->protocol !== AiProviderProtocol::OpenAI) {
            return null;
        }

        return new Message(MessageRole::SYSTEM, $context);
    }
}
