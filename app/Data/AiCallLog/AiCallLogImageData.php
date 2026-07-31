<?php

namespace App\Data\AiCallLog;

use Spatie\LaravelData\Data;

/**
 * AI 调用日志详情中的图片预览数据。
 */
class AiCallLogImageData extends Data
{
    public function __construct(
        public string $url,
        public ?string $name,
    ) {}
}
