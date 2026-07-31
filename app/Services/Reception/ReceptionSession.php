<?php

namespace App\Services\Reception;

use Illuminate\Support\Str;

/**
 * 生成和校验访客接待会话 token。
 */
class ReceptionSession
{
    public const string COOKIE_PREFIX = 'helmdesk_visitor_';

    private const int TOKEN_LENGTH = 32;

    /**
     * 生成新的访客会话 token。
     */
    public static function generate(): string
    {
        return Str::lower(Str::random(self::TOKEN_LENGTH));
    }

    /**
     * 校验并返回 cookie 里的访客会话 token。
     */
    public static function normalize(?string $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $trimmed = trim($raw);

        if (strlen($trimmed) !== self::TOKEN_LENGTH) {
            return null;
        }

        if (preg_match('/^[a-z0-9]+$/', $trimmed) !== 1) {
            return null;
        }

        return $trimmed;
    }
}
