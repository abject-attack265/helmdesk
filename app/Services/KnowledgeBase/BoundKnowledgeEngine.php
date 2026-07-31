<?php

namespace App\Services\KnowledgeBase;

use App\Data\KnowledgeBase\KnowledgeEngineConfigData;
use App\Enums\KnowledgeChunkingStrategy;
use App\Enums\KnowledgeIndexingStrategy;
use App\Models\AiModel;
use Illuminate\Support\Collection;

/**
 * 绑定到当前应用生效配置的知识库引擎视图。
 *
 * 由 KnowledgeEngine::current() / ::defaults() 即用即弃地创建，持有 KnowledgeEngineConfigData，
 * 把「索引策略 / 分块参数 / pin 的向量模型」三类运行时读取按生效配置应答。
 * Octane 安全：本对象只在调用栈内存活，绝不缓存到容器单例或静态属性。
 */
final class BoundKnowledgeEngine
{
    public function __construct(
        private readonly KnowledgeEngineConfigData $config,
    ) {}

    /**
     * 是否启用向量检索。
     */
    public function vectorEnabled(): bool
    {
        return $this->config->vector_index_enabled;
    }

    /**
     * 是否启用 RAPTOR 摘要树索引。
     */
    public function raptorEnabled(): bool
    {
        return $this->config->raptor_index_enabled;
    }

    /**
     * 当前启用的索引策略集合。
     *
     * @return list<KnowledgeIndexingStrategy>
     */
    public function enabledIndexingStrategies(): array
    {
        $strategies = [];

        if ($this->config->vector_index_enabled) {
            $strategies[] = KnowledgeIndexingStrategy::Vector;
        }

        if ($this->config->raptor_index_enabled) {
            $strategies[] = KnowledgeIndexingStrategy::Raptor;
        }

        return $strategies;
    }

    /**
     * 是否启用指定索引策略。
     */
    public function hasIndexingStrategy(KnowledgeIndexingStrategy $strategy): bool
    {
        return in_array($strategy, $this->enabledIndexingStrategies(), true);
    }

    /**
     * 当前分块策略。
     */
    public function chunkingStrategy(): KnowledgeChunkingStrategy
    {
        return KnowledgeChunkingStrategy::tryFrom($this->config->chunking_strategy)
            ?? KnowledgeChunkingStrategy::Fixed;
    }

    /**
     * 单块最大 token 数。
     */
    public function chunkMaxTokens(): int
    {
        return $this->config->chunk_max_tokens;
    }

    /**
     * 相邻块重叠 token 数。
     */
    public function chunkOverlapTokens(): int
    {
        return $this->config->chunk_overlap_tokens;
    }

    /**
     * pin 的向量维度（透传给 embeddings API 的 dimensions；null=模型默认维度）。
     */
    public function embeddingDimension(): ?int
    {
        return $this->config->embedding_dimension;
    }

    /**
     * pin 的向量模型（按 model_id）下、活跃且凭据完整的备份，打散后用于容灾/分摊。
     *
     * 维度不固定在模型上，而是统一取已解析的 embedding_dimension 在调用时透传；
     * 同 model_id 的活跃备份产出的向量空间一致、可互换轮询。
     *
     * @return Collection<int, AiModel>
     */
    public function pinnedEmbeddingModels(): Collection
    {
        $modelId = $this->config->embedding_model_id;
        if (blank($modelId)) {
            return collect();
        }

        return AiModel::query()
            ->with('provider')
            ->where('type', 'embedding')
            ->where('is_active', true)
            ->where('model_id', $modelId)
            ->get()
            ->filter(static fn (AiModel $model): bool => $model->provider !== null
                && $model->provider->hasCompleteCredentials())
            ->shuffle()
            ->values();
    }

    /**
     * 取 pin 规格下首个可用的 embedding 备份（打散后），无则返回 null。
     */
    public function pinnedEmbeddingModel(): ?AiModel
    {
        return $this->pinnedEmbeddingModels()->first();
    }

    /**
     * pin 的向量模型当前是否有可用备份。
     */
    public function hasPinnedEmbeddingModel(): bool
    {
        return $this->pinnedEmbeddingModels()->isNotEmpty();
    }
}
