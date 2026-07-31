<?php

namespace App\Services\KnowledgeBase\Parsing;

/**
 * 解析结果值对象，承载归一化 Markdown 文本和解析元数据。
 *
 * @phpstan-type Metadata array<string, scalar|array<int|string, mixed>|null>
 */
final readonly class ParsedDocument
{
    /**
     * 承载解析后的归一化 Markdown、内容格式标识与解析元数据。
     *
     * @param  Metadata  $metadata
     */
    public function __construct(
        public string $markdown,
        public string $contentFormat,
        public array $metadata = [],
    ) {}
}
