<?php

namespace App\Services\KnowledgeBase;

use Illuminate\Http\Client\Factory as HttpClient;
use RuntimeException;

/**
 * Cohere/Jina 式 rerank HTTP 客户端。
 *
 * 走与 chat / embedding 无关的独立 rerank 协议：
 *   POST <base_uri>/rerank  Authorization: Bearer <key>
 *   {"model": ..., "query": ..., "documents": [...], "top_n": N}
 *   → {"results": [{"index": 0, "relevance_score": 0.9}, ...]}
 *
 * 返回归一成 [{index, score}]（score = relevance_score）。
 */
class KnowledgeRerankClient
{
    private const int TIMEOUT_SECONDS = 28;

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * 调用 rerank 模型，返回 (index, score) 列表。
     *
     * @param  list<string>  $documents
     * @return list<array{index: int, score: float}>
     *
     * @throws RuntimeException base_uri 未配置或上游返回非成功状态
     */
    public function rerank(string $baseUri, string $key, string $modelId, string $query, array $documents, int $topN = 0): array
    {
        if ($documents === [] || trim($query) === '') {
            return [];
        }

        $baseUri = trim($baseUri);
        if ($baseUri === '') {
            throw new RuntimeException('rerank provider has no base_uri configured');
        }

        $payload = [
            'model' => $modelId,
            'query' => $query,
            'documents' => array_values($documents),
        ];
        if ($topN > 0) {
            $payload['top_n'] = $topN;
        }

        $response = $this->http
            ->withToken($key)
            ->timeout(self::TIMEOUT_SECONDS)
            ->post(rtrim($baseUri, '/').'/rerank', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('rerank upstream returned status '.$response->status());
        }

        $results = $response->json('results') ?? [];
        $out = [];
        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'index' => (int) ($row['index'] ?? -1),
                'score' => (float) ($row['relevance_score'] ?? 0.0),
            ];
        }

        return $out;
    }
}
