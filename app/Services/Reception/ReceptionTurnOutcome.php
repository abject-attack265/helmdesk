<?php

namespace App\Services\Reception;

/**
 * 一次接待 turn 流式消费的结果。
 *
 * - completed：推理正常跑完，text 为最终要投递给访客的 assistant 文本（可能为空，表示无文本产出）。
 * - preempted：消费途中检测到抢占信号，已丢弃本轮产物，不投递。
 */
final class ReceptionTurnOutcome
{
    private function __construct(
        public readonly string $status,
        public readonly string $text,
    ) {}

    /**
     * 正常完成，携带最终 assistant 文本。
     */
    public static function completed(string $text): self
    {
        return new self('completed', $text);
    }

    /**
     * 被抢占：丢弃产物。
     */
    public static function preempted(): self
    {
        return new self('preempted', '');
    }

    /**
     * 是否被抢占。
     */
    public function isPreempted(): bool
    {
        return $this->status === 'preempted';
    }

    /**
     * 是否正常完成。
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
