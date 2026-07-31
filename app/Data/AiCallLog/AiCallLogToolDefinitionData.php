<?php

namespace App\Data\AiCallLog;

use Spatie\LaravelData\Data;

/**
 * AI 调用日志详情中的可用工具定义。
 */
class AiCallLogToolDefinitionData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        /** 标识 respond 工具 */
        public bool $sent_to_visitor,
    ) {}
}
