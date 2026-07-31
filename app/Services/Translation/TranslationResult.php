<?php

namespace App\Services\Translation;

use Spatie\LaravelData\Data;

/** 统一表示译文、语言和供应商调用元数据。 */
class TranslationResult extends Data
{
    /** 创建统一翻译结果。 */
    public function __construct(
        public string $text,
        public string $source_lang,
        public string $target_lang,
        public string $provider_slug,
        public ?string $model,
        public int $latency_ms,
        public ?int $char_count,
    ) {}
}
