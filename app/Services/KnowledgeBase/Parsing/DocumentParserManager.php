<?php

namespace App\Services\KnowledgeBase\Parsing;

use RuntimeException;

/**
 * 文档解析入口；按注册顺序找到第一个 supports() 命中的解析器并调用。
 */
class DocumentParserManager
{
    /**
     * 注入按优先级排列的文档解析器集合。
     *
     * @param  iterable<DocumentParserInterface>  $parsers
     */
    public function __construct(
        private readonly iterable $parsers,
    ) {}

    /**
     * 解析磁盘文件并返回归一化 Markdown。
     *
     * @throws RuntimeException 文件不存在或无解析器支持。
     */
    public function parse(string $absoluteFilePath, ?string $mimeType = null, ?string $extension = null): ParsedDocument
    {
        if (! is_file($absoluteFilePath)) {
            throw new RuntimeException(sprintf('File not found for parsing: %s', $absoluteFilePath));
        }

        $normalizedMime = $mimeType !== null ? strtolower(trim($mimeType)) : null;
        $normalizedExt = $extension !== null ? strtolower(ltrim(trim($extension), '.')) : null;

        foreach ($this->parsers as $parser) {
            if ($parser->supports($normalizedMime, $normalizedExt)) {
                return $parser->parse($absoluteFilePath, $normalizedMime, $normalizedExt);
            }
        }

        throw new RuntimeException(sprintf(
            'No registered parser supports mime=%s extension=%s',
            $normalizedMime ?? 'unknown',
            $normalizedExt ?? 'unknown',
        ));
    }
}
