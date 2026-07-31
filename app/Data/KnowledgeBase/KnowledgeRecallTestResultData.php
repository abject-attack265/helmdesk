<?php

namespace App\Data\KnowledgeBase;

use Spatie\LaravelData\Data;

/**
 * 召回测试面板的检索响应。
 * 由 RunKnowledgeRecallTestAction 富集来源标题后以 JSON 形式返回给
 * resources/js/pages/knowledgeBase/KnowledgeRecallTestPanel.vue（走 useHttp，不触发页面导航）。
 *
 * 与发给 Agent 的 KnowledgeSearchResultData 区别：这里面向人，命中带可读标题/字段标签/诊断信息。
 */
class KnowledgeRecallTestResultData extends Data
{
    /**
     * @param  KnowledgeRecallSemanticHitData[]  $semantic_hits
     * @param  KnowledgeRecallGrepMatchData[]  $grep_matches
     */
    public function __construct(
        public string $mode,
        public array $semantic_hits,
        public array $grep_matches,
        public KnowledgeRecallDiagnosticsData $diagnostics,
    ) {}

    /**
     * 基于 SearchKnowledgeBaseAction 的原始结果与已解析的来源标题映射，组装面板用结果。
     *
     * @param  list<KnowledgeSearchSemanticHitData>  $semanticHits  KnowledgeSearchResultData::$semantic_hits
     * @param  list<KnowledgeSearchGrepMatchData>  $grepMatches  KnowledgeSearchResultData::$grep_matches
     * @param  array<string, mixed>  $debug  KnowledgeSearchResultData::$debug
     * @param  array<string, string>  $documentTitles  document_id => 文件名
     * @param  array<string, string>  $qaQuestions  qa_entry_id => 主问题
     */
    public static function fromSearchResult(
        string $mode,
        array $semanticHits,
        array $grepMatches,
        array $debug,
        array $documentTitles,
        array $qaQuestions,
    ): self {
        $semantic = array_map(
            static fn (KnowledgeSearchSemanticHitData $hit) => KnowledgeRecallSemanticHitData::fromHit($hit, $documentTitles, $qaQuestions),
            $semanticHits,
        );
        $grep = array_map(
            static fn (KnowledgeSearchGrepMatchData $match) => KnowledgeRecallGrepMatchData::fromMatch($match, $qaQuestions),
            $grepMatches,
        );

        return new self(
            mode: $mode,
            semantic_hits: $semantic,
            grep_matches: $grep,
            diagnostics: KnowledgeRecallDiagnosticsData::fromDebug($debug, $mode, count($semantic), count($grep)),
        );
    }
}
