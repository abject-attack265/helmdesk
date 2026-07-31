<?php

namespace App\Services\Realtime;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\PrivateChannel;

class MercureBroadcaster extends Broadcaster
{
    public function __construct(
        private readonly MercurePublisher $publisher,
    ) {}

    public function auth($request)
    {
        return null;
    }

    public function validAuthenticationResponse($request, $result)
    {
        return $result;
    }

    public function broadcast(array $channels, $event, array $payload = []): void
    {
        foreach ($channels as $channel) {
            $private = $channel instanceof PrivateChannel;
            $topic = (string) $channel;

            if ($private) {
                $topic = substr($topic, strlen('private-'));
            }

            $this->publisher->publish($topic, (string) $event, $payload, $private);
        }
    }
}
