<?php

namespace App\Services\AiChat;

use NeuronAI\Tools\ToolInterface;

/**
 * 将 NeuronAI 工具状态转换为前端 AI 对话使用的流式事件载荷。
 */
class AiChatToolStreamPayloadBuilder
{
    /**
     * 构造包含调用参数的 tool_call 载荷。
     *
     * @param  array<string, string>  $tool_display_names
     * @return array<string, mixed>
     */
    public function call(ToolInterface $tool, array $tool_display_names): array
    {
        return $this->withDisplayName([
            'type' => 'tool_call',
            'tool' => $tool->getName(),
            'args' => json_encode(
                (object) $tool->getInputs(),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
        ], $tool->getName(), $tool_display_names);
    }

    /**
     * 构造包含执行结果的 tool_result 载荷。
     *
     * @param  array<string, string>  $tool_display_names
     * @return array<string, mixed>
     */
    public function result(ToolInterface $tool, array $tool_display_names): array
    {
        return $this->withDisplayName([
            'type' => 'tool_result',
            'tool' => $tool->getName(),
            'content' => $tool->getResult(),
        ], $tool->getName(), $tool_display_names);
    }

    /**
     * 补充人类可读的工具名称。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $tool_display_names
     * @return array<string, mixed>
     */
    private function withDisplayName(
        array $payload,
        string $tool_name,
        array $tool_display_names,
    ): array {
        $display_name = $tool_display_names[$tool_name] ?? null;
        if ($display_name !== null) {
            $payload['tool_display'] = $display_name;
        }

        return $payload;
    }
}
