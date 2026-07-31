<?php

namespace App\Services\KnowledgeBase\Parsing;

use RuntimeException;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser as SmalotPdfParser;
use Throwable;

/**
 * PDF 解析器：基于 smalot/pdfparser，逐页抽取文本拼成 Markdown。
 *
 * 限制：
 *  - 不做 OCR；扫描件会得到空文本（上层会因 "no_segments" 走 Failed 路径）。
 *  - 不做表格还原；表格通常退化成空格分隔的行。
 *  - 大型 PDF（百兆级）解析会比较慢。
 */
class PdfDocumentParser implements DocumentParserInterface
{
    public function supports(?string $mimeType, ?string $extension): bool
    {
        if ($extension === 'pdf') {
            return true;
        }

        return $mimeType !== null && str_contains($mimeType, 'pdf');
    }

    public function parse(string $absoluteFilePath, ?string $mimeType = null, ?string $extension = null): ParsedDocument
    {
        try {
            $parser = new SmalotPdfParser;
            $document = $parser->parseFile($absoluteFilePath);
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf('Failed to parse PDF: %s', $exception->getMessage()), 0, $exception);
        }

        $pages = $document->getPages();
        $sections = [];

        foreach ($pages as $index => $page) {
            $text = $this->cleanupPageText($page->getText());
            if ($text === '') {
                continue;
            }

            $sections[] = sprintf("## Page %d\n\n%s", $index + 1, $text);
        }

        $title = $this->extractTitle($document);
        if ($title !== '' && $sections !== []) {
            array_unshift($sections, sprintf('# %s', $title));
        }

        $markdown = implode("\n\n", $sections);

        return new ParsedDocument(
            markdown: $markdown,
            contentFormat: 'markdown',
            metadata: [
                'parser' => 'pdf',
                'mime_type' => $mimeType,
                'extension' => $extension,
                'page_count' => count($pages),
                'title' => $title !== '' ? $title : null,
            ],
        );
    }

    private function cleanupPageText(string $text): string
    {
        $normalized = preg_replace("/[ \t]+/u", ' ', $text);
        $normalized = preg_replace("/\n{3,}/u", "\n\n", (string) $normalized);

        return trim((string) $normalized);
    }

    private function extractTitle(Document $document): string
    {
        try {
            $details = $document->getDetails();
        } catch (Throwable) {
            return '';
        }

        foreach (['Title', 'title'] as $key) {
            $candidate = $details[$key] ?? null;
            if (is_string($candidate)) {
                $trimmed = trim($candidate);
                if ($trimmed !== '') {
                    return $trimmed;
                }
            }
        }

        return '';
    }
}
