<?php

namespace App\Casts\Concerns;

use InvalidArgumentException;

/**
 * 解码数据库 JSON 对象列。
 */
trait DecodesJsonArrays
{
    /**
     * null 表示未设置；非空值必须是数组或 JSON 对象。
     *
     * @return array<string, mixed>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('数据库 JSON 列必须是 JSON 对象或 null。');
        }

        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('数据库 JSON 列必须解码为对象。');
        }

        return $decoded;
    }
}
