<?php

namespace App\Data\KnowledgeBase;

use Spatie\LaravelData\Data;

/**
 * 召回测试的诊断信息，回显本次检索实际走了哪几路 retriever、是否触发 rerank、嵌入是否失败。
 * 由 SearchKnowledgeBaseAction 产出的 debug 数组归一而来，帮助管理员判断「为什么这条没召回」。
 * 对应 resources/js/pages/knowledgeBase/KnowledgeRecallTestPanel.vue 顶部的诊断条。
 */
class KnowledgeRecallDiagnosticsData extends Data
{
    public function __construct(
        public string $mode,
        public int $semantic_count,
        public int $grep_count,
        public bool $fulltext,
        public bool $vector,
        public bool $raptor,
        public bool $rerank_enabled,
        public bool $rerank_applied,
        public bool $embedding_failed,
    ) {}

    /**
     * 从 SearchKnowledgeBaseAction 的 debug 输出抽取面板需要的诊断标记。
     *
     * @param  array<string, mixed>  $debug
     */
    public static function fromDebug(array $debug, string $mode, int $semanticCount, int $grepCount): self
    {
        /** @var array<string, mixed> $semantic */
        $semantic = is_array($debug['semantic'] ?? null) ? $debug['semantic'] : [];

        return new self(
            mode: $mode,
            semantic_count: $semanticCount,
            grep_count: $grepCount,
            fulltext: (bool) ($semantic['fulltext_enabled'] ?? false),
            vector: (bool) ($semantic['vector_enabled'] ?? false),
            raptor: (bool) ($semantic['raptor_enabled'] ?? false),
            rerank_enabled: (bool) ($semantic['rerank_enabled'] ?? false),
            rerank_applied: (bool) ($semantic['rerank_applied'] ?? false),
            embedding_failed: array_key_exists('embedding_error', $semantic),
        );
    }
}
