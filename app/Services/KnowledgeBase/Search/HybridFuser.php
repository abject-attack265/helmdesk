<?php

namespace App\Services\KnowledgeBase\Search;

/**
 * Reciprocal Rank Fusion (RRF) 融合器。
 *
 * 把不同 retriever（向量 / 全文 / Raptor）按 source 维度独立排好的命中流，
 * 按 node_id 维度合到一起，每条命中的最终得分 = sum( 1 / (k + rank_in_each_source) )。
 *
 * 这里只融合"按 node_id 可比"的语义类 retriever。grep 命中由于结构差异较大，
 * 不参与 RRF，而是单独以"匹配片段数组"形式返回。
 */
class HybridFuser
{
    /**
     * RRF 的平滑因子，业界惯用 60，能让前 5 名得分差异更明显，又不至于过度压缩。
     */
    private const int RRF_K = 60;

    /**
     * 按 RRF 合并多个 retriever 的命中。
     *
     * @param  list<KnowledgeSearchHit>  $hits
     * @return list<KnowledgeSearchHit>
     */
    public function fuse(array $hits, int $topK): array
    {
        if ($hits === [] || $topK <= 0) {
            return [];
        }

        $bestByNode = [];
        $rrfScores = [];
        $sourcesByNode = [];

        foreach ($hits as $hit) {
            $nodeId = $hit->knowledgeNodeId;
            $rrf = 1.0 / (self::RRF_K + $hit->rank);

            if (! isset($rrfScores[$nodeId])) {
                $rrfScores[$nodeId] = 0.0;
                $sourcesByNode[$nodeId] = [];
            }
            $rrfScores[$nodeId] += $rrf;
            $sourcesByNode[$nodeId][$hit->source] = max(
                $sourcesByNode[$nodeId][$hit->source] ?? 0.0,
                $hit->score,
            );

            // 保留每个 node 当前出现过的最佳代表条目（rank 最小即代表性最强）。
            if (! isset($bestByNode[$nodeId]) || $hit->rank < $bestByNode[$nodeId]->rank) {
                $bestByNode[$nodeId] = $hit;
            }
        }

        arsort($rrfScores);

        $output = [];
        $position = 0;
        foreach ($rrfScores as $nodeId => $score) {
            if ($position >= $topK) {
                break;
            }
            $position++;
            $best = $bestByNode[$nodeId];
            $metadata = $best->metadata;
            $metadata['rrf_score'] = $score;
            $metadata['fused_sources'] = $sourcesByNode[$nodeId];

            $output[] = $best->with([
                'source' => 'hybrid',
                'score' => $score,
                'rank' => $position,
                'metadata' => $metadata,
            ]);
        }

        return $output;
    }
}
