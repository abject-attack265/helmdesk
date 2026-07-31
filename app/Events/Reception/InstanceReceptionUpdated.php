<?php

namespace App\Events\Reception;

use App\Services\Realtime\MercureTopics;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** 发布收件箱会话变更。 */
class InstanceReceptionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $payload  坐席端会话变更全量载荷
     */
    public function __construct(
        public readonly array $payload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(MercureTopics::receptionInbox());
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
