<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * 规范化结构化输出中的通用字段值。
 */
class StructuredOutputNormalizer
{
    /**
     * 清理字符串列表中的空白项并重建连续索引。
     *
     * @param  list<string>  $value
     * @return list<string>
     */
    public static function stringList(array $value): array
    {
        $normalized = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new InvalidArgumentException('结构化输出列表只能包含字符串。');
            }

            $item = trim($item);
            if ($item !== '') {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }
}
