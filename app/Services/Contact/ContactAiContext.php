<?php

namespace App\Services\Contact;

use Illuminate\Validation\ValidationException;

/**
 * 整理联系人 AI 上下文。
 */
class ContactAiContext
{
    public const int MAX_BYTES = 65536;

    /**
     * 补齐更新时间并检查上下文大小。
     *
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>|null
     */
    public static function normalize(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }

        $normalized = $context;
        $normalized['_updated_at'] = now()->toIso8601String();

        self::assertWithinLimit($normalized);

        return $normalized;
    }

    /**
     * 合并两份 AI 上下文。
     *
     * @param  array<string, mixed>|null  $target
     * @param  array<string, mixed>|null  $merged
     * @return array<string, mixed>|null
     */
    public static function merge(?array $target, ?array $merged): ?array
    {
        if ($target === null && $merged === null) {
            return null;
        }

        if ($target === null) {
            return self::normalize($merged);
        }

        if ($merged === null) {
            return self::normalize($target);
        }

        return self::normalize(array_merge($merged, $target));
    }

    /**
     * 确认 AI 上下文没有超过存储上限。
     *
     * @param  array<string, mixed>  $context
     */
    public static function assertWithinLimit(array $context): void
    {
        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw ValidationException::withMessages([
                'ai_context' => __('contact.invalid_ai_context'),
            ]);
        }

        if (strlen($encoded) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'ai_context' => __('contact.ai_context_too_large'),
            ]);
        }
    }
}
