<?php

namespace App\Events\AiChat;

use App\Services\Realtime\MercureTopics;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** 发布 AI 助手流式片段。 */
class AiChatStreamChunk implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $payload  单个流式片段载荷
     */
    public function __construct(
        public readonly string $roundId,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(MercureTopics::aiChat($this->roundId));
    }

    public function broadcastAs(): string
    {
        return 'message';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
