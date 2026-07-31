<?php

namespace App\Data\Translation;

use App\Services\Translation\TranslationResult;
use Spatie\LaravelData\Data;

/**
 * 会话消息 payload 中保存的翻译结果。
 */
class MessageTranslationData extends Data
{
    /** 创建消息译文及供应商元数据。 */
    public function __construct(
        public string $text,
        public string $source_lang,
        public string $target_lang,
        public string $provider_slug,
        public int $latency_ms,
    ) {}

    /**
     * 从供应商翻译结果创建消息译文数据。
     */
    public static function fromTranslationResult(TranslationResult $result): self
    {
        return new self(
            text: $result->text,
            source_lang: $result->source_lang,
            target_lang: $result->target_lang,
            provider_slug: $result->provider_slug,
            latency_ms: $result->latency_ms,
        );
    }
}
