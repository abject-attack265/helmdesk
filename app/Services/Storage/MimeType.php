<?php

namespace App\Services\Storage;

/**
 * MIME 类型字符串处理工具。
 */
class MimeType
{
    /**
     * 返回去掉参数后缀并转小写的 MIME 类型。
     */
    public static function normalize(string $mimeType): string
    {
        return strtolower(trim(explode(';', $mimeType)[0]));
    }
}
