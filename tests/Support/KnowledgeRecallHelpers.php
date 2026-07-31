<?php

/*
 * 中文召回评测共享 helper。
 *
 * KnowledgeRecallBenchmarkTest（lexical 基线）与 Live/KnowledgeLiveRecallBenchmarkTest（活体）
 * 共用同一组指标计算与语料装载逻辑，避免两份重复实现。
 *
 * 评测语料：tests/Fixtures/Knowledge/zh_recall_corpus.json
 * 结构对齐 DuReader-Retrieval / C-MTEB，指标 Recall@K、MRR 与 BEIR 一致。
 */

/**
 * 加载迷你中文召回评测集。
 *
 * @return array{documents: list<array{id: string, title: string, content: string}>, queries: list<array{id: string, text: string, positive_doc_ids: list<string>}>}
 */
function loadChineseRecallCorpus(): array
{
    $payload = json_decode(
        (string) file_get_contents(__DIR__.'/../Fixtures/Knowledge/zh_recall_corpus.json'),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    return [
        'documents' => $payload['documents'],
        'queries' => $payload['queries'],
    ];
}

/**
 * Recall@K：top-K 命中数 / positive 总数。
 *
 * @param  list<string>  $retrievedDocIds
 * @param  list<string>  $positiveDocIds
 */
function recallAtK(array $retrievedDocIds, array $positiveDocIds, int $k): float
{
    if ($positiveDocIds === []) {
        return 0.0;
    }
    $top = array_slice($retrievedDocIds, 0, $k);
    $hit = count(array_intersect($top, $positiveDocIds));

    return $hit / count($positiveDocIds);
}

/**
 * Mean Reciprocal Rank @K：top-K 中首个正例的 1/rank（找不到记 0）。
 *
 * @param  list<string>  $retrievedDocIds
 * @param  list<string>  $positiveDocIds
 */
function reciprocalRankAtK(array $retrievedDocIds, array $positiveDocIds, int $k): float
{
    $top = array_slice($retrievedDocIds, 0, $k);
    foreach ($top as $index => $docId) {
        if (in_array($docId, $positiveDocIds, true)) {
            return 1.0 / ($index + 1);
        }
    }

    return 0.0;
}
