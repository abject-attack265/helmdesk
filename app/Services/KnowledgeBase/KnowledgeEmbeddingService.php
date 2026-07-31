<?php

namespace App\Services\KnowledgeBase;

use App\Exceptions\BusinessException;
use App\Models\AiModel;

/**
 * 嵌入模型调用统一入口。
 *
 * 负责把"调一次模型 → 拿一组向量"的细节（维度一致性校验、凭据解析）收敛到一处，
 * 让 Vector / Raptor / QA 三个索引器共用一份实现。
 *
 * 经 KnowledgeEmbeddingProviderFactory 映射到 NeuronAI embeddings provider 逐条嵌入；
 * 维度由模型响应推断（不强制 dimensions 参数）。
 */
class KnowledgeEmbeddingService
{
    public function __construct(
        private readonly KnowledgeEmbeddingProviderFactory $providers,
    ) {}

    /**
     * 嵌入一组文本，返回 [dimension, vectors]。
     * 任一文本返回维度与首条不一致时抛 BusinessException。
     *
     * @param  list<string>  $contents
     * @return array{0: int, 1: list<list<float>>}
     */
    public function embedTexts(AiModel $model, array $contents, ?int $dimensions = null): array
    {
        if ($contents === []) {
            return [0, []];
        }

        $provider = $this->providers->make($model, $dimensions);

        $dimension = 0;
        $vectors = [];
        foreach ($contents as $content) {
            $vector = array_map(static fn ($v): float => (float) $v, $provider->embedText($content));
            $vectorDimension = count($vector);
            if ($dimension === 0) {
                $dimension = $vectorDimension;
            } elseif ($vectorDimension !== $dimension) {
                throw new BusinessException(__('knowledge_base.documents.errors.embedding_failed'));
            }
            $vectors[] = $vector;
        }

        if ($dimension <= 0 || count($vectors) !== count($contents)) {
            throw new BusinessException(__('knowledge_base.documents.errors.embedding_failed'));
        }

        return [$dimension, $vectors];
    }
}
