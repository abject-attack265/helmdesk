<?php

namespace App\Services\KnowledgeBase\Parsing;

/**
 * Markdown 分块器。
 *
 * 规则：
 *  - 跟踪标题栈 (H1..H6)，每段输出携带 heading_path 帮助召回时定位。
 *  - 按段落 (空行分割) 累计 token；超过 maxTokens 输出一段。
 *  - overlapTokens > 0 时，下一段会从上一段尾部拷贝若干字符做软重叠。
 *  - token 计数采用粗略字符/汉字混合估算，避免引入额外 tokenizer 依赖；
 *    真实的模型 token 上限由 embed 阶段实际调用时校验。
 *
 * byteStart/byteEnd 基于输入 markdown 的 **字节** 偏移（UTF-8）。
 *
 * @phpstan-type Segment array{
 *     content: string,
 *     heading_path: list<string>,
 *     byte_start: int,
 *     byte_end: int,
 *     token_count: int,
 * }
 * @phpstan-type SentenceUnit array{
 *     content: string,
 *     heading_path: list<string>,
 *     byte_start: int,
 *     byte_end: int,
 *     token_count: int,
 * }
 * @phpstan-type OutlineNode array{
 *     heading: string,
 *     level: int,
 *     children?: list<array<string, mixed>>,
 * }
 */
class MarkdownChunker
{
    /**
     * 按段落累计 token 切分 markdown，输出带 heading_path 的段列表与大纲。
     *
     * @return array{segments: list<Segment>, outline: list<OutlineNode>}
     */
    public function chunk(string $markdown, int $maxTokens = 512, int $overlapTokens = 64): array
    {
        if ($maxTokens <= 0) {
            $maxTokens = 512;
        }
        if ($overlapTokens < 0) {
            $overlapTokens = 0;
        }
        if ($overlapTokens >= $maxTokens) {
            $overlapTokens = intdiv($maxTokens, 4);
        }

        $paragraphs = $this->splitIntoParagraphs($markdown);
        $segments = $this->buildSegments($paragraphs, $maxTokens, $overlapTokens);
        $outline = $this->extractOutline($markdown);

        return ['segments' => $segments, 'outline' => $outline];
    }

    /**
     * 仅抽取大纲，不分块；用于把大纲单独写到 knowledge_outlines。
     *
     * @return list<OutlineNode>
     */
    public function outline(string $markdown): array
    {
        return $this->extractOutline($markdown);
    }

    /**
     * 把 markdown 拆成句子级最小单元（带字节偏移与 heading_path），供语义分块按句合并。
     *
     * @return list<SentenceUnit>
     */
    public function sentenceUnits(string $markdown): array
    {
        $units = [];
        foreach ($this->splitIntoParagraphs($markdown) as $paragraph) {
            $cursor = 0;
            foreach ($this->splitIntoSentences($paragraph['text']) as $sentence) {
                $content = trim($sentence);
                if ($content === '') {
                    continue;
                }

                $localStart = strpos($paragraph['text'], $content, $cursor);
                if ($localStart === false) {
                    $localStart = $cursor;
                }
                $localEnd = $localStart + strlen($content);
                $cursor = $localEnd;

                $units[] = [
                    'content' => $content,
                    'heading_path' => $paragraph['headings'],
                    'byte_start' => $paragraph['byte_start'] + $localStart,
                    'byte_end' => $paragraph['byte_start'] + $localEnd,
                    'token_count' => $this->estimateTokens($content),
                ];
            }
        }

        return $units;
    }

    /**
     * 估算 token 数。经验值：英文约 4 char/token，CJK 字符约 1 token/字。
     */
    public function estimateTokens(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $asciiLike = 0;
        $cjkLike = 0;
        $length = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');
            $codePoint = $this->codePointOf($char);
            if ($codePoint >= 0x4E00 && $codePoint <= 0x9FFF) {
                $cjkLike++;
            } else {
                $asciiLike++;
            }
        }

        $approx = $cjkLike + intdiv($asciiLike + 3, 4);

        return $approx === 0 ? 1 : $approx;
    }

    /**
     * 按空行/标题切出段落，并跟踪标题栈给每段附上当前 heading_path 与字节偏移。
     *
     * @return list<array{text: string, byte_start: int, byte_end: int, headings: list<string>}>
     */
    private function splitIntoParagraphs(string $markdown): array
    {
        $headings = [];
        $currentHeadings = [];
        $paragraphs = [];
        $current = '';
        $currentStart = -1;
        $offset = 0;
        $lines = explode("\n", $markdown);
        $lastIndex = count($lines) - 1;

        $flush = function (int $endOffset) use (&$current, &$currentStart, &$paragraphs, &$currentHeadings): void {
            $text = trim($current);
            if ($text !== '') {
                $paragraphs[] = [
                    'text' => $text,
                    'byte_start' => $currentStart,
                    'byte_end' => $endOffset,
                    'headings' => $currentHeadings,
                ];
            }
            $current = '';
            $currentStart = -1;
        };

        foreach ($lines as $i => $line) {
            $lineOffset = $offset;
            $lineLength = strlen($line);
            $offset = $i < $lastIndex
                ? $lineOffset + $lineLength + 1
                : $lineOffset + $lineLength;

            $stripped = trim($line);

            $heading = $this->parseHeading($stripped);
            if ($heading !== null) {
                $flush($lineOffset);
                $level = max(1, min(6, $heading['level']));
                while (count($headings) >= $level) {
                    array_pop($headings);
                }
                while (count($headings) < $level - 1) {
                    $headings[] = '';
                }
                $headings[] = $heading['title'];
                $currentHeadings = $headings;

                continue;
            }

            if ($stripped === '') {
                $flush($lineOffset);

                continue;
            }

            if ($currentStart < 0) {
                $currentStart = $lineOffset;
                $currentHeadings = $headings;
            }
            if ($current !== '') {
                $current .= "\n";
            }
            $current .= $line;
        }

        $flush($offset);

        return $paragraphs;
    }

    /**
     * 将段落累加进缓冲区，超过 maxTokens 即落段，并按 overlapTokens 做软重叠。
     *
     * @param  list<array{text: string, byte_start: int, byte_end: int, headings: list<string>}>  $paragraphs
     * @return list<Segment>
     */
    private function buildSegments(array $paragraphs, int $maxTokens, int $overlapTokens): array
    {
        $segments = [];

        $bufText = '';
        $bufStart = -1;
        $bufEnd = -1;
        $bufHeadings = [];
        $bufTokens = 0;
        $overlapTail = '';

        $flush = function () use (&$segments, &$bufText, &$bufStart, &$bufEnd, &$bufHeadings, &$bufTokens, &$overlapTail, $overlapTokens): void {
            if ($bufText === '') {
                return;
            }
            $text = trim($bufText);
            if ($text === '') {
                $bufText = '';
                $bufStart = -1;
                $bufEnd = -1;
                $bufTokens = 0;
                $bufHeadings = [];

                return;
            }

            $segments[] = [
                'content' => $text,
                'heading_path' => $bufHeadings,
                'byte_start' => $bufStart,
                'byte_end' => $bufEnd,
                'token_count' => $bufTokens,
            ];

            $overlapTail = $overlapTokens > 0 ? $this->tailWithTokenBudget($text, $overlapTokens) : '';

            $bufText = '';
            $bufStart = -1;
            $bufEnd = -1;
            $bufTokens = 0;
            $bufHeadings = [];
        };

        foreach ($paragraphs as $p) {
            $pTokens = $this->estimateTokens($p['text']);

            if ($pTokens > $maxTokens) {
                $flush();
                $pieces = $this->splitLongParagraph($p['text'], $maxTokens, $overlapTokens);
                foreach ($pieces as $piece) {
                    $segments[] = [
                        'content' => $piece,
                        'heading_path' => $p['headings'],
                        'byte_start' => $p['byte_start'],
                        'byte_end' => $p['byte_end'],
                        'token_count' => $this->estimateTokens($piece),
                    ];
                }
                $overlapTail = '';

                continue;
            }

            if ($bufTokens + $pTokens > $maxTokens && $bufText !== '') {
                $flush();
                if ($overlapTail !== '') {
                    $bufText .= $overlapTail."\n\n";
                    $bufTokens += $this->estimateTokens($overlapTail);
                }
            }

            if ($bufStart < 0) {
                $bufStart = $p['byte_start'];
                $bufHeadings = $p['headings'];
            }
            if ($bufText !== '') {
                $bufText .= "\n\n";
            }
            $bufText .= $p['text'];
            $bufEnd = $p['byte_end'];
            $bufTokens += $pTokens;
        }
        $flush();

        return $segments;
    }

    /**
     * 扫描 markdown 各级标题，按层级嵌套还原成大纲树。
     *
     * @return list<OutlineNode>
     */
    private function extractOutline(string $markdown): array
    {
        /** @var list<array{node: array{heading: string, level: int, children: list<array<string, mixed>>}, level: int}> $stack */
        $stack = [];
        $roots = [];

        foreach (explode("\n", $markdown) as $line) {
            $heading = $this->parseHeading(trim($line));
            if ($heading === null) {
                continue;
            }

            $node = [
                'heading' => $heading['title'],
                'level' => $heading['level'],
                'children' => [],
            ];

            while ($stack !== [] && array_last($stack)['level'] >= $heading['level']) {
                array_pop($stack);
            }

            if ($stack === []) {
                $roots[] = $node;
                $stack[] = ['parent' => &$roots, 'index' => array_key_last($roots), 'level' => $heading['level']];

                continue;
            }

            $top = array_last($stack);
            $top['parent'][$top['index']]['children'][] = $node;
            $childIndex = array_key_last($top['parent'][$top['index']]['children']);
            $stack[] = [
                'parent' => &$top['parent'][$top['index']]['children'],
                'index' => $childIndex,
                'level' => $heading['level'],
            ];
        }

        return $this->cleanOutline($roots);
    }

    /**
     * 递归归一化大纲节点：收窄字段类型，children 为空时省略。
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<OutlineNode>
     */
    private function cleanOutline(array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $entry = [
                'heading' => (string) $node['heading'],
                'level' => (int) $node['level'],
            ];
            $children = $node['children'] ?? [];
            if ($children !== []) {
                $entry['children'] = $this->cleanOutline($children);
            }
            $result[] = $entry;
        }

        return $result;
    }

    /**
     * 解析 ATX 标题行，返回级别与标题文本；非标题行返回 null。
     *
     * @return array{level: int, title: string}|null
     */
    private function parseHeading(string $line): ?array
    {
        if ($line === '' || $line[0] !== '#') {
            return null;
        }

        $level = 0;
        $length = strlen($line);
        while ($level < $length && $line[$level] === '#') {
            $level++;
        }
        if ($level === 0 || $level > 6) {
            return null;
        }
        if ($level >= $length) {
            return ['level' => $level, 'title' => ''];
        }
        if ($line[$level] !== ' ' && $line[$level] !== "\t") {
            return null;
        }

        return ['level' => $level, 'title' => trim(substr($line, $level))];
    }

    private function tailWithTokenBudget(string $text, int $tokenBudget): string
    {
        if ($tokenBudget <= 0 || $text === '') {
            return '';
        }

        $length = mb_strlen($text, 'UTF-8');
        for ($i = $length - 1; $i >= 0; $i--) {
            $piece = mb_substr($text, $i, $length - $i, 'UTF-8');
            if ($this->estimateTokens($piece) >= $tokenBudget) {
                return $piece;
            }
        }

        return $text;
    }

    /**
     * 把超长段落逐级按分隔符（换行/句末标点）拆短，仍超限再按字符窗口硬切。
     *
     * @return list<string>
     */
    private function splitLongParagraph(string $text, int $maxTokens, int $overlapTokens): array
    {
        if ($this->estimateTokens($text) <= $maxTokens) {
            return [$text];
        }

        $separators = ["\n", '。', '！', '？', '; ', '. '];
        $pieces = [$text];

        foreach ($separators as $separator) {
            $next = [];
            foreach ($pieces as $piece) {
                if ($this->estimateTokens($piece) <= $maxTokens) {
                    $next[] = $piece;

                    continue;
                }
                $parts = $this->splitAfter($piece, $separator);
                $current = '';
                $currentTokens = 0;
                foreach ($parts as $part) {
                    $partTokens = $this->estimateTokens($part);
                    if ($currentTokens + $partTokens > $maxTokens && $current !== '') {
                        $next[] = trim($current);
                        $current = '';
                        $currentTokens = 0;
                    }
                    $current .= $part;
                    $currentTokens += $partTokens;
                }
                if ($current !== '') {
                    $next[] = trim($current);
                }
            }
            $pieces = $next;
            $allUnder = true;
            foreach ($pieces as $piece) {
                if ($this->estimateTokens($piece) > $maxTokens) {
                    $allUnder = false;
                    break;
                }
            }
            if ($allUnder) {
                break;
            }
        }

        $final = [];
        foreach ($pieces as $piece) {
            if ($this->estimateTokens($piece) <= $maxTokens) {
                $final[] = $piece;

                continue;
            }
            $length = mb_strlen($piece, 'UTF-8');
            $ratio = max(0.25, $this->estimateTokens($piece) / max(1, $length));
            $chunkSize = max(1, (int) floor($maxTokens / $ratio));
            $overlap = $overlapTokens > 0 ? (int) floor($overlapTokens / $ratio) : 0;

            for ($start = 0; $start < $length;) {
                $end = min($length, $start + $chunkSize);
                $final[] = trim(mb_substr($piece, $start, $end - $start, 'UTF-8'));
                if ($end >= $length) {
                    break;
                }
                $start = $end - $overlap;
                if ($start <= 0) {
                    $start = $end;
                }
            }
        }

        return $final;
    }

    /**
     * 类似 strtok 的"保留分隔符"版本：每段末尾保留其分隔符。
     *
     * @return list<string>
     */
    private function splitAfter(string $text, string $separator): array
    {
        if ($separator === '' || $text === '') {
            return [$text];
        }

        $result = [];
        $offset = 0;
        $sepLen = strlen($separator);
        while (($pos = strpos($text, $separator, $offset)) !== false) {
            $result[] = substr($text, $offset, $pos - $offset + $sepLen);
            $offset = $pos + $sepLen;
        }
        if ($offset < strlen($text)) {
            $result[] = substr($text, $offset);
        }

        return $result;
    }

    /**
     * 按中英文句末标点把段落切成句子；无标点时整段作为一句返回。
     *
     * @return list<string>
     */
    private function splitIntoSentences(string $text): array
    {
        $content = trim($text);
        if ($content === '') {
            return [];
        }

        preg_match_all('/[^。！？!?；;.!?]+[。！？!?；;.!?]?/u', $content, $matches);
        $sentences = array_values(array_filter(array_map(
            static fn (string $sentence): string => trim($sentence),
            $matches[0] ?? [],
        ), static fn (string $sentence): bool => $sentence !== ''));

        return $sentences === [] ? [$content] : $sentences;
    }

    private function codePointOf(string $char): int
    {
        $codes = unpack('N', mb_convert_encoding($char, 'UCS-4BE', 'UTF-8'));

        return $codes === false ? 0 : (int) $codes[1];
    }
}
