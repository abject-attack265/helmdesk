<?php

namespace App\Services\Conversation;

use InvalidArgumentException;

/**
 * 解码会话事件表中的 payload 对象。
 */
class ConversationEventPayloadDecoder
{
    /**
     * null 表示事件没有附加数据；非空值必须是数组或 JSON 对象。
     *
     * @return array<string, mixed>
     */
    public static function decode(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if ($payload === null) {
            return [];
        }

        if (! is_string($payload)) {
            throw new InvalidArgumentException('会话事件 payload 必须是 JSON 对象或 null。');
        }

        $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('会话事件 payload 必须解码为对象。');
        }

        return $decoded;
    }
}
