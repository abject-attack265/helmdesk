<?php

namespace App\Services\Realtime;

use Closure;

/**
 * 将应用实时事件发布到 FrankenPHP Worker 注入的 Mercure Hub。
 */
class MercurePublisher
{
    /** @var Closure(string, string, array<string, mixed>, bool): string */
    private readonly Closure $publishUpdate;

    /**
     * 初始化 Mercure 更新发布器。
     *
     * @param  null|Closure(string, string, array<string, mixed>, bool): string  $publishUpdate
     */
    public function __construct(?Closure $publishUpdate = null)
    {
        $this->publishUpdate = $publishUpdate ?? static fn (
            string $topic,
            string $event,
            array $payload,
            bool $private,
        ): string => \mercure_publish(
            $topic,
            json_encode($payload, JSON_THROW_ON_ERROR),
            $private,
            null,
            $event,
        );
    }

    /**
     * 发布一个带事件类型和可见性约束的 Mercure 更新。
     *
     * @param  array<string, mixed>  $payload
     */
    public function publish(string $topic, string $event, array $payload, bool $private = false): void
    {
        ($this->publishUpdate)($topic, $event, $payload, $private);
    }
}
