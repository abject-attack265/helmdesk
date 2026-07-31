<?php

use App\Data\KnowledgeBase\KnowledgeEngineConfigData;
use App\Enums\AiModelPurpose;
use App\Enums\KnowledgeChunkingStrategy;
use App\Models\AiModel;
use App\Settings\GeneralSettings;

/*
 * 知识库引擎测试助手。
 *
 * 知识库引擎配置为纯应用级：每个应用持有完整配置，未单独配置的应用按代码/环境级默认
 * （config/knowledge.defaults）兜底。这些助手通过设置该默认值，让「按工厂创建、未单独配置」的
 * 应用一步到位获得可用的向量规格 / 索引开关 / 分块参数，供知识库集成测试调用；
 * 需要测试单个应用的差异化配置时用 setInstanceKnowledgeConfig()。
 */

/**
 * Seed 一个活跃、凭据完整的 embedding AiModel，并把代码默认（config/knowledge.defaults）的
 * embedding_model_id pin 成与之匹配、embedding_dimension 固定、vector_index_enabled=true。
 *
 * 默认建出 model_id=text-embedding-3-small 的可用向量模型，维度固定为 1536，
 * 让未单独配置的应用经 KnowledgeEngine::for() 解析出可用模型、向量检索/索引生效。
 *
 * @param  array<string, mixed>  $overrides  覆写 AiModel 字段（如 model_id）；dimensions 写入默认维度
 */
function pinKnowledgeEmbeddingModel(array $overrides = []): AiModel
{
    $modelId = $overrides['model_id'] ?? 'text-embedding-3-small';
    $dimensions = array_key_exists('dimensions', $overrides)
        ? $overrides['dimensions']
        : 1536;
    unset($overrides['dimensions']);

    $model = makeAiModel(AiModelPurpose::Embedding);
    $model->forceFill(array_merge($overrides, [
        'model_id' => $modelId,
    ]))->save();
    $model->load('provider');

    config()->set('knowledge.defaults.embedding_model_id', $modelId);
    config()->set('knowledge.defaults.embedding_dimension', $dimensions);
    config()->set('knowledge.defaults.vector_index_enabled', true);

    return $model->refresh();
}

/**
 * 调整代码默认（config/knowledge.defaults）的引擎字段（向量/RAPTOR 开关、分块策略与参数）。
 *
 * 只覆写显式传入的字段，未传入的保持现值；供需要切换 RAPTOR / 分块策略的测试调用。
 */
function setKnowledgeEngine(
    ?bool $vectorIndexEnabled = null,
    ?bool $raptorIndexEnabled = null,
    KnowledgeChunkingStrategy|string|null $chunkingStrategy = null,
    ?int $chunkMaxTokens = null,
    ?int $chunkOverlapTokens = null,
): void {
    if ($vectorIndexEnabled !== null) {
        config()->set('knowledge.defaults.vector_index_enabled', $vectorIndexEnabled);
    }
    if ($raptorIndexEnabled !== null) {
        config()->set('knowledge.defaults.raptor_index_enabled', $raptorIndexEnabled);
    }
    if ($chunkingStrategy !== null) {
        config()->set('knowledge.defaults.chunking_strategy', $chunkingStrategy instanceof KnowledgeChunkingStrategy
            ? $chunkingStrategy->value
            : $chunkingStrategy);
    }
    if ($chunkMaxTokens !== null) {
        config()->set('knowledge.defaults.chunk_max_tokens', $chunkMaxTokens);
    }
    if ($chunkOverlapTokens !== null) {
        config()->set('knowledge.defaults.chunk_overlap_tokens', $chunkOverlapTokens);
    }
}

/**
 * 给应用写入一份完整的知识库引擎配置（在代码默认之上合并传入字段）；
 * 传入空数组等价于清除应用配置（回到代码默认）。
 *
 * @param  array<string, mixed>  $config  KnowledgeEngineConfigData 字段子集
 */
function setInstanceKnowledgeConfig(GeneralSettings $app, array $config = []): void
{
    if ($config === []) {
        $app->knowledge_engine_config = null;
    } else {
        $base = KnowledgeEngineConfigData::default()->toArray();
        $app->knowledge_engine_config = KnowledgeEngineConfigData::from(array_merge($base, $config));
    }
    $app->save();
}
