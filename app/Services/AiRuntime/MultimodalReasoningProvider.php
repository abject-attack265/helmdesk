<?php

declare(strict_types=1);

namespace App\Services\AiRuntime;

use NeuronAI\Providers\MessageMapperInterface;

/**
 * 面向 OpenAI 兼容的国产多模态推理模型（MiniMax、小米 MiMo 等）的 provider。
 *
 * 支持 video_url 视频输入扩展（见 messageMapper）。
 * 结构化输出（structured() / json_object）由厂商 API 层面保证 JSON 与推理内容隔离。
 */
class MultimodalReasoningProvider extends OpenAICompatibleProvider
{
    /**
     * 返回支持视频块的消息映射器。
     */
    public function messageMapper(): MessageMapperInterface
    {
        return $this->messageMapper ??= new OpenAiVideoMessageMapper;
    }
}
