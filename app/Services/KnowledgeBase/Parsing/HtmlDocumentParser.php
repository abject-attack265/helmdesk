<?php

namespace App\Services\KnowledgeBase\Parsing;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use LogicException;
use RuntimeException;

/**
 * HTML 文档解析器，移除可执行内容并把标题、正文、列表和表格文本归一化为 Markdown。
 */
class HtmlDocumentParser implements DocumentParserInterface
{
    /**
     * 判断 MIME 或扩展名是否属于 HTML 文档。
     */
    public function supports(?string $mimeType, ?string $extension): bool
    {
        return in_array($extension, ['html', 'htm'], true)
            || $mimeType === 'text/html'
            || $mimeType === 'application/xhtml+xml';
    }

    /**
     * 解析 HTML 文件并返回不含脚本和样式的 Markdown。
     */
    public function parse(string $absoluteFilePath, ?string $mimeType = null, ?string $extension = null): ParsedDocument
    {
        $html = file_get_contents($absoluteFilePath);
        if ($html === false) {
            throw new RuntimeException(sprintf('Failed to read HTML file: %s', $absoluteFilePath));
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8">'.$html,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new RuntimeException(sprintf('Failed to parse HTML file: %s', $absoluteFilePath));
        }

        $xpath = new DOMXPath($document);
        $this->removeUnsafeNodes($xpath);

        $lines = [];
        $blocks = $xpath->query(
            '//body//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6 or self::p or self::li or self::pre or self::tr]'
            .'[not(ancestor::p or ancestor::li or ancestor::pre or ancestor::tr)]',
        );
        if ($blocks === false) {
            throw new LogicException('Invalid HTML block XPath expression.');
        }
        foreach ($blocks as $block) {
            if (! $block instanceof DOMElement) {
                throw new LogicException('HTML block query returned a non-element node.');
            }

            $line = $this->renderBlock($block);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        if ($lines === []) {
            $bodyNodes = $xpath->query('//body');
            if ($bodyNodes === false) {
                throw new LogicException('Invalid HTML body XPath expression.');
            }
            $body = $bodyNodes->item(0);
            $fallback = $body instanceof DOMNode ? $this->normalizeText($body->textContent) : '';
            if ($fallback !== '') {
                $lines[] = $fallback;
            }
        }

        $titleNodes = $xpath->query('//head/title');
        if ($titleNodes === false) {
            throw new LogicException('Invalid HTML title XPath expression.');
        }
        $titleNode = $titleNodes->item(0);
        $title = $titleNode instanceof DOMNode ? $this->normalizeText($titleNode->textContent) : '';

        return new ParsedDocument(
            markdown: implode("\n\n", $lines),
            contentFormat: 'markdown',
            metadata: [
                'parser' => 'html',
                'mime_type' => $mimeType,
                'extension' => $extension,
                'title' => $title !== '' ? $title : null,
            ],
        );
    }

    /**
     * 删除不会进入知识索引的脚本、样式及模板节点。
     */
    private function removeUnsafeNodes(DOMXPath $xpath): void
    {
        $nodes = $xpath->query('//script | //style | //noscript | //template');
        if ($nodes === false) {
            throw new LogicException('Invalid HTML unsafe-node XPath expression.');
        }

        foreach (iterator_to_array($nodes) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * 将单个块级元素转换为 Markdown 行。
     */
    private function renderBlock(DOMElement $element): string
    {
        $text = $this->normalizeText($element->textContent);
        if ($text === '') {
            return '';
        }

        $tag = strtolower($element->tagName);
        if (preg_match('/^h([1-6])$/', $tag, $matches) === 1) {
            return str_repeat('#', (int) $matches[1]).' '.$text;
        }

        if ($tag === 'li') {
            return '- '.$text;
        }

        if ($tag === 'pre') {
            return "```\n".trim($element->textContent)."\n```";
        }

        if ($tag === 'tr') {
            $cells = [];
            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['th', 'td'], true)) {
                    $cells[] = str_replace('|', ' ', $this->normalizeText($child->textContent));
                }
            }

            return $cells === [] ? '' : '| '.implode(' | ', $cells).' |';
        }

        return $text;
    }

    /**
     * 把连续空白折叠为单个空格。
     */
    private function normalizeText(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
