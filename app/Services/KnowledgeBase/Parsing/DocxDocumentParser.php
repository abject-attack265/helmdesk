<?php

namespace App\Services\KnowledgeBase\Parsing;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use Throwable;

/**
 * .docx 解析器：使用 phpoffice/phpword 抽取段落与标题，归一化为 Markdown。
 *
 * 支持：
 *  - Word Title（depth=N → Markdown #*(N+1)）
 *  - 段落 / 项目符号列表
 *  - 表格转 Markdown 简单形式（不支持合并单元格的复杂样式）
 *
 * 不支持：图片、复杂样式、SmartArt。
 */
class DocxDocumentParser implements DocumentParserInterface
{
    private const array SUPPORTED_EXTENSIONS = [
        'docx',
        'doc',
    ];

    private const array SUPPORTED_MIMES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
    ];

    public function supports(?string $mimeType, ?string $extension): bool
    {
        if ($extension !== null && in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            return true;
        }

        return $mimeType !== null && in_array($mimeType, self::SUPPORTED_MIMES, true);
    }

    public function parse(string $absoluteFilePath, ?string $mimeType = null, ?string $extension = null): ParsedDocument
    {
        try {
            $reader = $extension === 'doc' ? 'MsDoc' : 'Word2007';
            $document = IOFactory::load($absoluteFilePath, $reader);
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf('Failed to parse Word document: %s', $exception->getMessage()), 0, $exception);
        }

        $lines = [];
        foreach ($document->getSections() as $section) {
            $this->collectContainer($section, $lines);
        }

        $markdown = trim(implode("\n\n", array_filter($lines, static fn (string $line) => $line !== '')));

        return new ParsedDocument(
            markdown: $markdown,
            contentFormat: 'markdown',
            metadata: [
                'parser' => 'docx',
                'mime_type' => $mimeType,
                'extension' => $extension,
                'section_count' => count($document->getSections()),
            ],
        );
    }

    /**
     * 遍历容器（section / 单元格等）下的所有元素，逐个归一化为 Markdown 行。
     *
     * @param  list<string>  $lines
     */
    private function collectContainer(AbstractContainer $container, array &$lines): void
    {
        foreach ($container->getElements() as $element) {
            $this->collectElement($element, $lines);
        }
    }

    /**
     * 按元素类型（标题/换行/文本/列表/表格/嵌套容器）分派，追加对应 Markdown 行。
     *
     * @param  list<string>  $lines
     */
    private function collectElement(object $element, array &$lines): void
    {
        if ($element instanceof Title) {
            $depth = max(1, min(6, (int) ($element->getDepth() ?: 1) + 1));
            $text = $this->stringifyTitleText($element);
            if ($text !== '') {
                $lines[] = str_repeat('#', $depth).' '.$text;
            }

            return;
        }

        if ($element instanceof TextBreak) {
            $lines[] = '';

            return;
        }

        if ($element instanceof TextRun) {
            $text = $this->stringifyTextRun($element);
            if ($text !== '') {
                $lines[] = $text;
            }

            return;
        }

        if ($element instanceof ListItem) {
            $text = trim((string) $element->getTextObject()?->getText());
            if ($text !== '') {
                $lines[] = '- '.$text;
            }

            return;
        }

        if (method_exists($element, 'getText')) {
            $text = trim((string) $element->getText());
            if ($text !== '') {
                $lines[] = $text;
            }

            return;
        }

        if ($element instanceof AbstractContainer) {
            $this->collectContainer($element, $lines);

            return;
        }

        if (method_exists($element, 'getRows')) {
            $this->collectTable($element, $lines);
        }
    }

    private function stringifyTextRun(TextRun $run): string
    {
        $buffer = '';
        foreach ($run->getElements() as $part) {
            if (method_exists($part, 'getText')) {
                $buffer .= (string) $part->getText();
            }
        }

        return trim($buffer);
    }

    private function stringifyTitleText(Title $title): string
    {
        $text = $title->getText();
        if ($text instanceof TextRun) {
            return $this->stringifyTextRun($text);
        }

        return trim((string) $text);
    }

    /**
     * 把 Word 表格转成 Markdown 表格（首行后插入分隔行，不处理合并单元格）。
     *
     * @param  list<string>  $lines
     */
    private function collectTable(object $table, array &$lines): void
    {
        $rows = [];
        foreach ($table->getRows() as $row) {
            $cells = [];
            foreach ($row->getCells() as $cell) {
                $cellLines = [];
                $this->collectContainer($cell, $cellLines);
                $cells[] = trim(implode(' ', $cellLines));
            }
            $rows[] = '| '.implode(' | ', $cells).' |';
        }

        if ($rows === []) {
            return;
        }

        $headerSeparator = '|'.str_repeat(' --- |', count($table->getRows()[0]->getCells()));
        array_splice($rows, 1, 0, [$headerSeparator]);
        $lines[] = implode("\n", $rows);
    }
}
