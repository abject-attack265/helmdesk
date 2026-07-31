<?php

use App\Models\AiModel;
use App\Services\KnowledgeBase\BoundKnowledgeEngine;
use App\Services\KnowledgeBase\KnowledgeEngine;
use Database\Factories\AiModelFactory;
use Database\Factories\AiProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 把代码默认（config/knowledge.defaults）pin 成指定 model_id + 维度并取默认引擎视图（维度独立于模型，单独固定）。
 */
function pinSettings(?string $modelId, ?int $dimension): BoundKnowledgeEngine
{
    config()->set('knowledge.defaults.embedding_model_id', $modelId);
    config()->set('knowledge.defaults.embedding_dimension', $dimension);
    config()->set('knowledge.defaults.vector_index_enabled', true);

    return app(KnowledgeEngine::class)->defaults();
}

test('pinnedEmbeddingModels 只含同 model_id + 活跃 + 凭据完整的备份', function () {
    // 同 model_id 的备份分属不同供应商（ai_provider_id + model_id + purpose 唯一），互为容灾。
    $matchA = AiModelFactory::new()->forProvider(AiProviderFactory::new()->create())->embedding()
        ->state(['model_id' => 'text-embedding-3-small'])->create();
    $matchB = AiModelFactory::new()->forProvider(AiProviderFactory::new()->create())->embedding()
        ->state(['model_id' => 'text-embedding-3-small'])->create();

    // 不同 model_id。
    AiModelFactory::new()->forProvider(AiProviderFactory::new()->create())->embedding()
        ->state(['model_id' => 'text-embedding-3-large'])->create();
    // 停用。
    AiModelFactory::new()->forProvider(AiProviderFactory::new()->create())->embedding()->inactive()
        ->state(['model_id' => 'text-embedding-3-small'])->create();
    // 缺凭据的供应商。
    AiModelFactory::new()->forProvider(AiProviderFactory::new()->withoutCredentials()->create())->embedding()
        ->state(['model_id' => 'text-embedding-3-small'])->create();

    $engine = pinSettings('text-embedding-3-small', 1536);

    $ids = $engine->pinnedEmbeddingModels()->pluck('id')->map(static fn ($id) => (string) $id)->sort()->values()->all();

    expect($ids)->toBe(collect([(string) $matchA->id, (string) $matchB->id])->sort()->values()->all())
        ->and($engine->hasPinnedEmbeddingModel())->toBeTrue()
        ->and($engine->embeddingDimension())->toBe(1536)
        ->and($engine->pinnedEmbeddingModel())->toBeInstanceOf(AiModel::class);
});

test('维度由 settings 单独固定，与模型解耦', function () {
    $match = AiModelFactory::new()->forProvider(AiProviderFactory::new()->create())->embedding()
        ->state(['model_id' => 'custom-embed'])->create();

    // 维度 pin 为 null：仍按 model_id 匹配模型，仅 embeddingDimension 为 null。
    $engine = pinSettings('custom-embed', null);

    $ids = $engine->pinnedEmbeddingModels()->pluck('id')->map(static fn ($id) => (string) $id)->all();

    expect($ids)->toBe([(string) $match->id])
        ->and($engine->embeddingDimension())->toBeNull();

    // 同一模型可配不同维度，由 settings 决定。
    $engine = pinSettings('custom-embed', 1024);
    expect($engine->embeddingDimension())->toBe(1024)
        ->and($engine->pinnedEmbeddingModel()?->id)->toBe($match->id);
});

test('未 pin 模型时返回空且 hasPinnedEmbeddingModel 为 false', function () {
    AiModelFactory::new()->embedding()
        ->state(['model_id' => 'text-embedding-3-small'])->create();

    $engine = pinSettings(null, null);

    expect($engine->pinnedEmbeddingModels())->toBeEmpty()
        ->and($engine->pinnedEmbeddingModel())->toBeNull()
        ->and($engine->hasPinnedEmbeddingModel())->toBeFalse();
});
