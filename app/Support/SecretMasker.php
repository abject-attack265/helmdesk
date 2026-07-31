<?php

namespace App\Support;

/**
 * 生成界面展示使用的密钥脱敏文本。
 */
class SecretMasker
{
    /**
     * 长密钥保留首尾各八个字符，短密钥隐藏全部内容。
     */
    public static function forDisplay(?string $secret): ?string
    {
        $value = trim((string) $secret);
        if ($value === '') {
            return null;
        }
        if (strlen($value) <= 16) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 8).'********'.substr($value, -8);
    }
}
