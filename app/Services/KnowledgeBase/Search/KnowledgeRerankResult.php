<?php

namespace App\Services\KnowledgeBase\Search;

/**
 * KnowledgeReranker 的结果结构。
 *
 * applied=true 表示真正调用了外部 rerank 模型并按其得分排序，hits[*].metadata['rerank_score']
 * 含外部模型给出的分数；applied=false 表示退回到调用方原序，hits 仅按 topK 截断。
 * SearchKnowledgeBaseAction 把 applied 直接写到 debug.rerank_applied 给前端 / 观测使用。
 *
 * errorCode 仅在 applied=false 且发生过降级时给出稳定码，原始异常落到服务端日志而不向上传播：
 *  - 'model_missing'：应用未配置 rerank 模型；
 *  - 'remote_unavailable'：远端调用失败或超时；
 *  - 'empty_response'：外部模型返回 results 为空或无可用 index。
 */
final class KnowledgeRerankResult
{
    /**
     * 承载重排后的命中列表、是否真正应用了外部重排，以及降级时的稳定错误码。
     *
     * @param  list<KnowledgeSearchHit>  $hits
     */
    public function __construct(
        public readonly array $hits,
        public readonly bool $applied,
        public readonly ?string $errorCode = null,
    ) {}
}
