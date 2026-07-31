<?php

namespace App\Services\KnowledgeBase\Parsing;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Excel 解析器（xlsx / xls / csv）。
 *
 * 每个 Sheet 输出为一段：
 *   ## Sheet 名
 *
 *   | 列1 | 列2 | 列3 |
 *   | --- | --- | --- |
 *   | a   | b   | c   |
 *
 * 第一行被默认当作表头。空行会被压缩掉。
 */
class XlsxDocumentParser implements DocumentParserInterface
{
    private const array SUPPORTED_EXTENSIONS = [
        'xlsx',
        'xls',
        'csv',
        'ods',
    ];

    private const array SUPPORTED_MIMES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/csv',
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
            $spreadsheet = IOFactory::load($absoluteFilePath);
        } catch (Throwable $exception) {
            throw new RuntimeException(sprintf('Failed to parse spreadsheet: %s', $exception->getMessage()), 0, $exception);
        }

        $sections = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rendered = $this->renderSheet($sheet);
            if ($rendered === '') {
                continue;
            }
            $sections[] = $rendered;
        }

        $markdown = trim(implode("\n\n", $sections));

        return new ParsedDocument(
            markdown: $markdown,
            contentFormat: 'markdown',
            metadata: [
                'parser' => 'xlsx',
                'mime_type' => $mimeType,
                'extension' => $extension,
                'sheet_count' => $spreadsheet->getSheetCount(),
            ],
        );
    }

    private function renderSheet(Worksheet $sheet): string
    {
        $rows = $sheet->toArray(null, true, false, false);
        $rows = array_values(array_filter($rows, static function (array $row): bool {
            foreach ($row as $cell) {
                if ($cell !== null && (string) $cell !== '') {
                    return true;
                }
            }

            return false;
        }));

        if ($rows === []) {
            return '';
        }

        $width = max(array_map('count', $rows));
        $normalized = array_map(function (array $row) use ($width): array {
            $padded = array_pad($row, $width, null);

            return array_map(function ($value): string {
                $stringValue = $value === null ? '' : (string) $value;

                return str_replace(['|', "\n", "\r"], [' ', ' ', ' '], $stringValue);
            }, $padded);
        }, $rows);

        $title = trim($sheet->getTitle());
        $lines = $title !== '' ? ['## '.$title, ''] : [];

        $lines[] = '| '.implode(' | ', $normalized[0]).' |';
        $lines[] = '|'.str_repeat(' --- |', $width);
        foreach (array_slice($normalized, 1) as $row) {
            $lines[] = '| '.implode(' | ', $row).' |';
        }

        return implode("\n", $lines);
    }
}
