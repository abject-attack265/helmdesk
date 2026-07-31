<?php

namespace App\Data\Contact;

use App\Support\StructuredOutputNormalizer;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

/**
 * 收件箱联系人上下文面板展示的接手简报。
 */
class ContactHandoffBriefData extends Data
{
    /**
     * 创建接手简报展示数据。
     *
     * @param  list<string>  $next_actions
     * @param  array<string, array<string, mixed>>  $translations
     */
    public function __construct(
        public string $brief,
        public array $next_actions,
        public string $source_locale,
        public array $translations,
        public string $updated_at,
    ) {}

    /**
     * 从 contacts.ai_context 中提取联系人接手简报。
     *
     * @param  array<string, mixed>|null  $context
     */
    public static function fromContext(?array $context): ?self
    {
        if ($context === null || ! array_key_exists('handoff_brief', $context)) {
            return null;
        }

        $briefData = $context['handoff_brief'];
        if (! is_array($briefData)) {
            throw new InvalidArgumentException('联系人接手简报必须是对象。');
        }

        $nextActions = $briefData['next_actions'] ?? null;
        if (! is_array($nextActions)) {
            throw new InvalidArgumentException('联系人接手简报 next_actions 必须是数组。');
        }

        $translations = $briefData['translations'] ?? null;
        if (! is_array($translations)) {
            throw new InvalidArgumentException('联系人接手简报 translations 必须是对象。');
        }

        return new self(
            brief: self::requiredString($briefData, 'brief'),
            next_actions: StructuredOutputNormalizer::stringList($nextActions),
            source_locale: self::requiredString($briefData, 'source_locale'),
            translations: $translations,
            updated_at: self::requiredString($briefData, 'updated_at'),
        );
    }

    /**
     * 读取接手简报中的必填非空字符串。
     *
     * @param  array<string, mixed>  $briefData
     */
    private static function requiredString(array $briefData, string $field): string
    {
        $value = $briefData[$field] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("联系人接手简报 {$field} 必须是非空字符串。");
        }

        return trim($value);
    }
}
