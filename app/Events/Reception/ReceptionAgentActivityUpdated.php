<?php

namespace App\Events\Reception;

use App\Services\Realtime\MercureTopics;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 向会话公开主题发布接待方活动状态。
 *
 * 状态聚合人工页面、AI debounce 与 AI turn 活动来源。
 */
class ReceptionAgentActivityUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * 承载会话活动状态及其单调版本。
     */
    public function __construct(
        public readonly string $conversationId,
        public readonly bool $active,
        public readonly int $holdMilliseconds,
        public readonly int $revision,
    ) {}

    /**
     * 返回访客会话的 Mercure 主题。
     */
    public function broadcastOn(): Channel
    {
        return new Channel(MercureTopics::receptionConversation($this->conversationId));
    }

    /**
     * 使用接待方活动事件名。
     */
    public function broadcastAs(): string
    {
        return 'agent_activity';
    }

    /**
     * 返回接待方活动状态载荷。
     *
     * @return array{conversation_id: string, active: bool, hold_ms: int, revision: int}
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'active' => $this->active,
            'hold_ms' => $this->holdMilliseconds,
            'revision' => $this->revision,
        ];
    }
}
