<?php

namespace App\Services\Conversation;

use InvalidArgumentException;

/**
 * 解码会话消息表中的 payload 对象。
 */
class ConversationMessagePayloadDecoder
{
    /**
     * null 表示消息没有附加数据；非空值必须是数组或 JSON 对象。
     *
     * @return array<string, mixed>|null
     */
    public static function decode(mixed $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload)) {
            throw new InvalidArgumentException('会话消息 payload 必须是 JSON 对象或 null。');
        }

        $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('会话消息 payload 必须解码为对象。');
        }

        return $decoded;
    }
}
