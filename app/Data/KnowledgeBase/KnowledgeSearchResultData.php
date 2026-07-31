<?php

namespace App\Data\KnowledgeBase;

use App\Services\KnowledgeBase\Search\GrepMatch;
use App\Services\KnowledgeBase\Search\KnowledgeSearchHit;
use Spatie\LaravelData\Data;

/**
 * Agent 知识库检索响应。
 *
 * 之所以把 semantic 与 grep 拆成两个数组返回，是因为 hybrid 模式下 Agent 同时拿到两类命中：
 *  - semantic_hits：来自向量 / 全文 / RAPTOR / rerank 的语义结果；
 *  - grep_matches：来自类 grep 的字面匹配，带行号 / 列号 / 上下文；
 *  - mode：echo 回模型本次调用所选的检索模式；
 *  - debug：可观测字段（rerank/raptor/vector 是否参与等），不强制要求 Agent 使用，便于排错。
 */
class KnowledgeSearchResultData extends Data
{
    /**
     * @param  KnowledgeSearchSemanticHitData[]  $semantic_hits
     * @param  KnowledgeSearchGrepMatchData[]  $grep_matches
     * @param  array<string, mixed>  $debug
     */
    public function __construct(
        public string $mode,
        public array $semantic_hits,
        public array $grep_matches,
        public array $debug = [],
    ) {}

    /**
     * 把检索流水线产出的服务层命中转换为传输 Data。
     *
     * @param  list<KnowledgeSearchHit>  $semanticHits
     * @param  list<GrepMatch>  $grepMatches
     * @param  array<string, mixed>  $debug
     */
    public static function fromHits(string $mode, array $semanticHits, array $grepMatches, array $debug = []): self
    {
        return new self(
            mode: $mode,
            semantic_hits: array_map(static fn (KnowledgeSearchHit $hit) => KnowledgeSearchSemanticHitData::fromHit($hit), $semanticHits),
            grep_matches: array_map(static fn (GrepMatch $match) => KnowledgeSearchGrepMatchData::fromMatch($match), $grepMatches),
            debug: $debug,
        );
    }
}
