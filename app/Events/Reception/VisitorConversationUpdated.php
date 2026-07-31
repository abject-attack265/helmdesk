<?php

namespace App\Events\Reception;

use App\Services\Realtime\MercureTopics;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** 发布访客端会话变更。 */
class VisitorConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $payload  访客端会话变更载荷（含最新接待 state）
     */
    public function __construct(
        public readonly string $conversationId,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel(MercureTopics::receptionConversation($this->conversationId));
    }

    public function broadcastAs(): string
    {
        return 'reception';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
