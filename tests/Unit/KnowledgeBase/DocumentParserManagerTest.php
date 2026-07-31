<?php

use App\Services\KnowledgeBase\Parsing\DocumentParserManager;
use App\Services\KnowledgeBase\Parsing\DocxDocumentParser;
use App\Services\KnowledgeBase\Parsing\HtmlDocumentParser;
use App\Services\KnowledgeBase\Parsing\PdfDocumentParser;
use App\Services\KnowledgeBase\Parsing\TextDocumentParser;

it('使用 TextDocumentParser 处理纯文本/Markdown 文件', function () {
    $manager = new DocumentParserManager([new TextDocumentParser]);
    $path = tempnam(sys_get_temp_dir(), 'kb-');
    file_put_contents($path, "# 标题\n\n正文。");

    try {
        $parsed = $manager->parse($path, 'text/markdown', 'md');
        expect($parsed->markdown)->toContain('标题')
            ->and($parsed->contentFormat)->toBe('markdown')
            ->and($parsed->metadata['parser'])->toBe('text');
    } finally {
        @unlink($path);
    }
});

it('解析失败时抛出 RuntimeException', function () {
    // 文件不存在
    $manager = new DocumentParserManager([new TextDocumentParser]);
    expect(fn () => $manager->parse('/tmp/__definitely_not_here__.txt', 'text/plain', 'txt'))
        ->toThrow(RuntimeException::class);

    // 无解析器命中
    $emptyManager = new DocumentParserManager([]);
    $path = tempnam(sys_get_temp_dir(), 'kb-');
    file_put_contents($path, 'hello');
    try {
        expect(fn () => $emptyManager->parse($path, 'application/pdf', 'pdf'))
            ->toThrow(RuntimeException::class);
    } finally {
        @unlink($path);
    }
});

it('CRLF 行结束符会被归一化为 LF', function () {
    $parser = new TextDocumentParser;
    $path = tempnam(sys_get_temp_dir(), 'kb-');
    file_put_contents($path, "line1\r\nline2\r\n");

    try {
        $parsed = $parser->parse($path, 'text/plain', 'txt');
        expect($parsed->markdown)->toContain("line1\nline2")
            ->and($parsed->markdown)->not->toContain("\r");
    } finally {
        @unlink($path);
    }
});

it('HTML 解析会保留正文结构并排除脚本与样式', function () {
    $parser = new HtmlDocumentParser;
    $path = tempnam(sys_get_temp_dir(), 'kb-html-');
    file_put_contents($path, <<<'HTML'
<!doctype html>
<html>
<head><title>退款手册</title><style>.secret { display: none; }</style></head>
<body>
<h1>退款政策</h1>
<p>订单支付后七天内可以申请退款。</p>
<script>window.stolen = document.cookie;</script>
</body>
</html>
HTML);

    try {
        $parsed = $parser->parse($path, 'text/html', 'html');

        expect($parsed->markdown)->toContain('# 退款政策')
            ->and($parsed->markdown)->toContain('七天内可以申请退款')
            ->and($parsed->markdown)->not->toContain('document.cookie')
            ->and($parsed->markdown)->not->toContain('display: none')
            ->and($parsed->metadata['parser'])->toBe('html')
            ->and($parsed->metadata['title'])->toBe('退款手册');
    } finally {
        @unlink($path);
    }
});

it('DOCX 解析会提取标题和正文', function () {
    $path = tempnam(sys_get_temp_dir(), 'kb-docx-');
    buildKnowledgeTestDocx($path);

    try {
        $parsed = (new DocxDocumentParser)->parse(
            $path,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'docx',
        );

        expect($parsed->markdown)->toContain('安装说明')
            ->and($parsed->markdown)->toContain('运行安装程序')
            ->and($parsed->metadata['parser'])->toBe('docx');
    } finally {
        @unlink($path);
    }
});

it('PDF 解析会提取页面文本', function () {
    $path = tempnam(sys_get_temp_dir(), 'kb-pdf-');
    file_put_contents($path, buildKnowledgeTestPdf('Refund policy allows seven days.'));

    try {
        $parsed = (new PdfDocumentParser)->parse($path, 'application/pdf', 'pdf');

        expect($parsed->markdown)->toContain('Refund policy allows seven days.')
            ->and($parsed->metadata['parser'])->toBe('pdf')
            ->and($parsed->metadata['page_count'])->toBe(1);
    } finally {
        @unlink($path);
    }
});

/**
 * 构造带单页 Helvetica 文本的最小 PDF 测试文件。
 */
function buildKnowledgeTestPdf(string $text): string
{
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $stream = "BT /F1 12 Tf 72 720 Td ({$escaped}) Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $number = $index + 1;
        $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
    $pdf .= "0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf
        ."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n"
        ."startxref\n{$xrefOffset}\n%%EOF\n";
}

/**
 * 构造包含标题和正文的最小 DOCX 测试文件。
 */
function buildKnowledgeTestDocx(string $path): void
{
    $archive = new ZipArchive;
    if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('无法创建 DOCX 测试文件。');
    }

    $archive->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML);
    $archive->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML);
    $archive->addFromString('word/document.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>安装说明</w:t></w:r></w:p>
    <w:p><w:r><w:t>运行安装程序并完成初始化。</w:t></w:r></w:p>
    <w:sectPr/>
  </w:body>
</w:document>
XML);
    $archive->close();
}
