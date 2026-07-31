<?php

namespace App\Services\Reception;

/**
 * 单接待轮次的投递计数。
 *
 * respond 工具每成功投递一条过渡气泡就 +1，供 RunReceptionTurnJob 判断本轮是否已向访客
 * 产出过可见消息：用于「最终文本为空且一条 respond 都没发」的零产出守卫（记日志）。
 */
class ReceptionTurnDelivery
{
    private int $count = 0;

    /**
     * 记一次成功投递。
     */
    public function markDelivered(): void
    {
        $this->count++;
    }

    /**
     * 本轮是否已向访客投递过至少一条消息。
     */
    public function delivered(): bool
    {
        return $this->count > 0;
    }

    /**
     * 本轮已投递的消息条数。
     */
    public function count(): int
    {
        return $this->count;
    }
}
