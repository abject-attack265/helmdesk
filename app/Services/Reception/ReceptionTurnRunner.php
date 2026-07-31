<?php

namespace App\Services\Reception;

use App\Exceptions\ModelAttemptTimeoutException;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;

/**
 * 消费 AI 接待事件流，在片段边界检查抢占和单次尝试时限，并提取最终答复。
 *
 * 工具调用前的正文属于中间陈述，最终结果只保留最后一次工具调用之后的文本。
 */
class ReceptionTurnRunner
{
    /**
     * 注入抢占信号。
     */
    public function __construct(
        private readonly ReceptionPreemptionSignal $preemption,
    ) {}

    /**
     * 消费一段 chunk 流，返回本轮结果。
     *
     * 超时只在事件到达时检查，首个事件之前的连接时限由队列任务控制。
     *
     * @param  iterable<object>  $events  NeuronAI AgentHandler::events() 产出的 chunk 流
     * @param  float|null  $timeoutSeconds  per-attempt wall-clock 时限（秒）；null 表示不限时
     *
     * @throws ModelAttemptTimeoutException 本次推理在 chunk 边界检测到已超过 $timeoutSeconds
     */
    public function consume(iterable $events, string $conversationId, string $turnId, ?float $timeoutSeconds = null): ReceptionTurnOutcome
    {
        $text = '';
        $deadline = $timeoutSeconds !== null ? microtime(true) + $timeoutSeconds : null;

        foreach ($events as $chunk) {
            if ($this->preemption->isPreempted($conversationId, $turnId)) {
                return ReceptionTurnOutcome::preempted();
            }

            if ($deadline !== null && microtime(true) > $deadline) {
                throw new ModelAttemptTimeoutException(
                    elapsed_seconds: $timeoutSeconds + (microtime(true) - $deadline),
                    timeout_seconds: $timeoutSeconds,
                );
            }

            if ($chunk instanceof TextChunk) {
                $text .= $chunk->content;

                continue;
            }

            if ($chunk instanceof ToolCallChunk) {
                // 工具调用前的中间文本不是最终答复，清空累积，最终答复来自最后一次工具结果之后。
                $text = '';
            }
        }

        // 循环只在每个 chunk 前检查抢占，最后一个 chunk 之后到流结束之间仍有窗口：
        // 若新访客消息此刻打断，需在投递前再查一次，否则会把这轮已过时的回复和新一轮回复一并发出。
        if ($this->preemption->isPreempted($conversationId, $turnId)) {
            return ReceptionTurnOutcome::preempted();
        }

        return ReceptionTurnOutcome::completed(trim($text));
    }
}
