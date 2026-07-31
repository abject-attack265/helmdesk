<?php

namespace App\Services\KnowledgeBase\Search;

use App\Enums\KnowledgeDocumentParseStatus;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeQaAnswer;
use App\Models\KnowledgeQaEntry;
use App\Models\KnowledgeQaQuestion;

/**
 * 在指定知识库的文档与问答内容中执行不区分大小写的字面检索。
 *
 * knowledgeBaseIds 已由调用方限制到当前应用；结果携带行列与 UTF-8 字节偏移，
 * hybrid 模式将这些命中作为独立结果返回。
 */
class GrepRetriever
{
    /**
     * 单条命中前后的上下文字符数。
     */
    private const int CONTEXT_WINDOW = 80;

    /**
     * 每条 query 在单个数据源的命中上限。
     */
    private const int MAX_HITS_PER_QUERY_PER_SOURCE = 8;

    /**
     * 单次检索的总命中上限。
     */
    public const int TOTAL_HITS_HARD_LIMIT = 50;

    /**
     * 在指定知识库范围内对多条 query 做字面检索，合并文档与 QA 命中并按上限截断。
     *
     * @param  list<string>  $knowledgeBaseIds  已由调用方校验过可访问范围
     * @param  list<string>  $queries
     * @return list<GrepMatch>
     */
    public function retrieve(array $knowledgeBaseIds, array $queries, int $topK): array
    {
        $cleanedQueries = $this->cleanQueries($queries);
        if ($cleanedQueries === [] || $knowledgeBaseIds === [] || $topK <= 0) {
            return [];
        }

        $hits = [];
        foreach ($cleanedQueries as $query) {
            $docHits = $this->grepDocuments($knowledgeBaseIds, $query);
            $qaHits = $this->grepQaEntries($knowledgeBaseIds, $query);
            $hits = array_merge($hits, $docHits, $qaHits);
            if (count($hits) >= self::TOTAL_HITS_HARD_LIMIT) {
                $hits = array_slice($hits, 0, self::TOTAL_HITS_HARD_LIMIT);
                break;
            }
        }

        if (count($hits) > $topK) {
            $hits = array_slice($hits, 0, $topK);
        }

        return $hits;
    }

    /**
     * 去除空白 query 并去重，避免重复 query 产生重复命中。
     *
     * @param  list<string>  $queries
     * @return list<string>
     */
    private function cleanQueries(array $queries): array
    {
        $output = [];
        foreach ($queries as $query) {
            $trimmed = trim((string) $query);
            if ($trimmed === '') {
                continue;
            }
            if (! in_array($trimmed, $output, true)) {
                $output[] = $trimmed;
            }
        }

        return $output;
    }

    /**
     * 把 query 转成 LIKE 粗筛用的 needle：小写化并转义 % / _ / \ 通配符，保证按字面匹配。
     */
    private function likeNeedle(string $query): string
    {
        return '%'.addcslashes(mb_strtolower($query), '\\%_').'%';
    }

    /**
     * 在已解析文档的 parsed_content 中字面检索 query，逐处命中生成 GrepMatch。
     *
     * @param  list<string>  $knowledgeBaseIds
     * @return list<GrepMatch>
     */
    private function grepDocuments(array $knowledgeBaseIds, string $query): array
    {
        $documents = KnowledgeDocument::query()
            ->whereIn('knowledge_base_id', $knowledgeBaseIds)
            ->where('parse_status', KnowledgeDocumentParseStatus::Succeeded)
            ->whereNotNull('parsed_content')
            ->whereRaw("LOWER(parsed_content) LIKE ? ESCAPE '\\'", [$this->likeNeedle($query)])
            ->orderByDesc('updated_at')
            ->limit(self::TOTAL_HITS_HARD_LIMIT)
            ->get(['id', 'knowledge_base_id', 'original_filename', 'parsed_content']);

        $hits = [];
        foreach ($documents as $document) {
            $hits = array_merge($hits, $this->buildMatches(
                knowledgeBaseId: (string) $document->knowledge_base_id,
                field: 'document.parsed_content',
                query: $query,
                text: (string) $document->parsed_content,
                documentId: (string) $document->id,
                documentTitle: (string) $document->original_filename,
            ));
            if (count($hits) >= self::TOTAL_HITS_HARD_LIMIT) {
                return array_slice($hits, 0, self::TOTAL_HITS_HARD_LIMIT);
            }
        }

        return $hits;
    }

    /**
     * 在 QA 词条的主问题、相似问与答案中字面检索 query，逐字段逐处命中生成 GrepMatch。
     *
     * @param  list<string>  $knowledgeBaseIds
     * @return list<GrepMatch>
     */
    private function grepQaEntries(array $knowledgeBaseIds, string $query): array
    {
        $needle = $this->likeNeedle($query);

        $entries = KnowledgeQaEntry::query()
            ->whereIn('knowledge_base_id', $knowledgeBaseIds)
            ->whereRaw("LOWER(question) LIKE ? ESCAPE '\\'", [$needle])
            ->limit(self::TOTAL_HITS_HARD_LIMIT)
            ->get(['id', 'knowledge_base_id', 'question']);

        $hits = [];
        foreach ($entries as $entry) {
            $hits = array_merge($hits, $this->buildMatches(
                knowledgeBaseId: (string) $entry->knowledge_base_id,
                field: 'qa_entry.question',
                query: $query,
                text: (string) $entry->question,
                qaEntryId: (string) $entry->id,
            ));
            if (count($hits) >= self::TOTAL_HITS_HARD_LIMIT) {
                return array_slice($hits, 0, self::TOTAL_HITS_HARD_LIMIT);
            }
        }

        $similarQuestions = KnowledgeQaQuestion::query()
            ->whereHas('entry', static function ($q) use ($knowledgeBaseIds): void {
                $q->whereIn('knowledge_base_id', $knowledgeBaseIds);
            })
            ->whereRaw("LOWER(question) LIKE ? ESCAPE '\\'", [$needle])
            ->with('entry:id,knowledge_base_id')
            ->limit(self::TOTAL_HITS_HARD_LIMIT)
            ->get(['id', 'knowledge_qa_entry_id', 'question']);

        foreach ($similarQuestions as $question) {
            /** @var KnowledgeQaQuestion $question */
            $entry = $question->entry;
            if ($entry === null) {
                continue;
            }
            $hits = array_merge($hits, $this->buildMatches(
                knowledgeBaseId: (string) $entry->knowledge_base_id,
                field: 'qa_entry.similar_question',
                query: $query,
                text: (string) $question->question,
                qaEntryId: (string) $entry->id,
                qaQuestionId: (string) $question->id,
            ));
            if (count($hits) >= self::TOTAL_HITS_HARD_LIMIT) {
                return array_slice($hits, 0, self::TOTAL_HITS_HARD_LIMIT);
            }
        }

        $answers = KnowledgeQaAnswer::query()
            ->whereHas('entry', static function ($q) use ($knowledgeBaseIds): void {
                $q->whereIn('knowledge_base_id', $knowledgeBaseIds);
            })
            ->whereRaw("LOWER(answer) LIKE ? ESCAPE '\\'", [$needle])
            ->with('entry:id,knowledge_base_id')
            ->limit(self::TOTAL_HITS_HARD_LIMIT)
            ->get(['id', 'knowledge_qa_entry_id', 'answer']);

        foreach ($answers as $answer) {
            /** @var KnowledgeQaAnswer $answer */
            $entry = $answer->entry;
            if ($entry === null) {
                continue;
            }
            $hits = array_merge($hits, $this->buildMatches(
                knowledgeBaseId: (string) $entry->knowledge_base_id,
                field: 'qa_entry.answer',
                query: $query,
                text: (string) $answer->answer,
                qaEntryId: (string) $entry->id,
                qaAnswerId: (string) $answer->id,
            ));
            if (count($hits) >= self::TOTAL_HITS_HARD_LIMIT) {
                return array_slice($hits, 0, self::TOTAL_HITS_HARD_LIMIT);
            }
        }

        return $hits;
    }

    /**
     * 对一段文本跑字面匹配，把每处命中包装成携带来源标识的 GrepMatch。
     *
     * @return list<GrepMatch>
     */
    private function buildMatches(
        string $knowledgeBaseId,
        string $field,
        string $query,
        string $text,
        ?string $documentId = null,
        ?string $documentTitle = null,
        ?string $qaEntryId = null,
        ?string $qaQuestionId = null,
        ?string $qaAnswerId = null,
    ): array {
        $hits = [];
        foreach ($this->findMatchesInText($text, $query, self::MAX_HITS_PER_QUERY_PER_SOURCE) as $position) {
            $hits[] = new GrepMatch(
                knowledgeBaseId: $knowledgeBaseId,
                documentId: $documentId,
                documentTitle: $documentTitle,
                qaEntryId: $qaEntryId,
                qaQuestionId: $qaQuestionId,
                qaAnswerId: $qaAnswerId,
                field: $field,
                query: $query,
                line: $position['line'],
                column: $position['column'],
                byteStart: $position['byte_start'],
                byteEnd: $position['byte_end'],
                match: $position['match'],
                contextBefore: $position['context_before'],
                contextAfter: $position['context_after'],
            );
        }

        return $hits;
    }

    /**
     * 在文本中按字面（大小写不敏感）寻找 query，返回带行号 / 列号 / 上下文的匹配描述。
     *
     * @return list<array{
     *     line: int,
     *     column: int,
     *     byte_start: int,
     *     byte_end: int,
     *     match: string,
     *     context_before: string,
     *     context_after: string,
     * }>
     */
    private function findMatchesInText(string $haystack, string $needle, int $maxMatches): array
    {
        if ($haystack === '' || $needle === '' || $maxMatches <= 0) {
            return [];
        }

        $haystackLength = mb_strlen($haystack, 'UTF-8');
        $needleLength = mb_strlen($needle, 'UTF-8');

        $matches = [];
        $cursor = 0;
        while ($cursor < $haystackLength) {
            $hitCharacter = mb_stripos($haystack, $needle, $cursor, 'UTF-8');
            if ($hitCharacter === false) {
                break;
            }

            $prefix = mb_substr($haystack, 0, $hitCharacter, 'UTF-8');
            $match = mb_substr($haystack, $hitCharacter, $needleLength, 'UTF-8');
            $byteStart = strlen($prefix);
            $byteEnd = $byteStart + strlen($match);
            $lastNewline = mb_strrpos($prefix, "\n", 0, 'UTF-8');
            $line = substr_count($prefix, "\n") + 1;
            $column = $lastNewline === false
                ? $hitCharacter + 1
                : mb_strlen(mb_substr($prefix, $lastNewline + 1, null, 'UTF-8'), 'UTF-8') + 1;

            $beforeLength = min(self::CONTEXT_WINDOW, $hitCharacter);
            $contextBefore = mb_substr(
                $haystack,
                $hitCharacter - $beforeLength,
                $beforeLength,
                'UTF-8',
            );
            $contextAfter = mb_substr(
                $haystack,
                $hitCharacter + $needleLength,
                self::CONTEXT_WINDOW,
                'UTF-8',
            );

            $matches[] = [
                'line' => $line,
                'column' => $column,
                'byte_start' => $byteStart,
                'byte_end' => $byteEnd,
                'match' => $match,
                'context_before' => $contextBefore,
                'context_after' => $contextAfter,
            ];

            if (count($matches) >= $maxMatches) {
                break;
            }

            $cursor = $hitCharacter + $needleLength;
        }

        return $matches;
    }
}
