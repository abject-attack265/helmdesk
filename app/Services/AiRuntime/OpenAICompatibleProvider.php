<?php

namespace App\Services\AiRuntime;

use NeuronAI\Chat\Messages\Message;
use NeuronAI\Providers\OpenAILike;

/**
 * 面向 OpenAI 兼容网关（DeepSeek / Qwen / OpenRouter 等）的 provider。
 *
 * NeuronAI 的 OpenAI provider 在 structured() 里用 response_format=json_schema，
 * 这是 OpenAI 官方 API 的专有能力，多数兼容端点不支持（DeepSeek 直接报
 * "This response_format type is unavailable now"）。这里降级为最低公分母方案：
 * response_format=json_object + 把 JSON schema 拼进 system prompt 约束输出，
 * 与 NeuronAI 自带 Deepseek provider 的实现一致。chat / stream 行为不变。
 */
class OpenAICompatibleProvider extends OpenAILike
{
    /**
     * 以 json_object + 提示词约束执行结构化输出。
     *
     * @param  Message|Message[]  $messages
     * @param  array<string, mixed>  $response_format
     */
    public function structured(array|Message $messages, string $class, array $response_format): Message
    {
        // 与 vendor 的 HandleStructured 一致:结构化输出对 parameters/system 的改写仅在本次调用内生效,
        // 用 finally 还原,避免 json_object 与 schema 提示词泄漏到之后复用同一 provider 实例的 chat/stream。
        $originalParameters = $this->parameters;
        $originalSystem = $this->system;

        $this->parameters = array_merge($this->parameters, [
            'response_format' => ['type' => 'json_object'],
        ]);

        $this->system = ($this->system ?? '')
            .PHP_EOL.'# OUTPUT FORMAT CONSTRAINTS'.PHP_EOL
            .'Generate a json respecting this schema: '.json_encode($response_format);

        $messages = is_array($messages) ? $messages : [$messages];

        try {
            return $this->chat(...$messages);
        } finally {
            $this->parameters = $originalParameters;
            $this->system = $originalSystem;
        }
    }
}
