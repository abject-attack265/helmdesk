<?php

namespace App\Services\KnowledgeBase\Parsing;

use RuntimeException;

/**
 * 纯文本 / Markdown 解析器，支持文本 MIME、文本扩展名及未提供格式信息的输入。
 */
class TextDocumentParser implements DocumentParserInterface
{
    private const array KNOWN_EXTENSIONS = [
        'txt',
        'md',
        'markdown',
        'text',
        'log',
        'csv',
    ];

    private const array TEXT_MIME_PREFIXES = [
        'text/',
    ];

    public function supports(?string $mimeType, ?string $extension): bool
    {
        if ($extension !== null && in_array($extension, self::KNOWN_EXTENSIONS, true)) {
            return true;
        }

        if ($mimeType !== null) {
            foreach (self::TEXT_MIME_PREFIXES as $prefix) {
                if (str_starts_with($mimeType, $prefix)) {
                    return true;
                }
            }
        }

        return $mimeType === null && $extension === null;
    }

    public function parse(string $absoluteFilePath, ?string $mimeType = null, ?string $extension = null): ParsedDocument
    {
        $raw = @file_get_contents($absoluteFilePath);
        if ($raw === false) {
            throw new RuntimeException(sprintf('Failed to read text file: %s', $absoluteFilePath));
        }

        $stripped = preg_replace("/^\xEF\xBB\xBF/", '', $raw) ?? $raw;
        $markdown = str_replace(["\r\n", "\r"], "\n", $stripped);

        return new ParsedDocument(
            markdown: $markdown,
            contentFormat: 'markdown',
            metadata: [
                'parser' => 'text',
                'mime_type' => $mimeType,
                'extension' => $extension,
                'byte_size' => strlen($raw),
            ],
        );
    }
}
