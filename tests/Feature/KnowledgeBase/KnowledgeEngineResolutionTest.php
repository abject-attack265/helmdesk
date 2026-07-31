<?php

use App\Data\KnowledgeBase\KnowledgeEngineConfigData;
use App\Enums\KnowledgeChunkingStrategy;
use App\Services\KnowledgeBase\KnowledgeEngine;
use Database\Factories\AiModelFactory;
use Database\Factories\AiProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 设定代码默认（config/knowledge.defaults）：向量开、维度 1536、fixed 分块、512/64、RAPTOR 关。
 */
function seedKnowledgeDefaults(): void
{
    config()->set('knowledge.defaults', [
        'embedding_model_id' => 'default-embed',
        'embedding_dimension' => 1536,
        'vector_index_enabled' => true,
        'raptor_index_enabled' => false,
        'chunking_strategy' => KnowledgeChunkingStrategy::Fixed->value,
        'chunk_max_tokens' => 512,
        'chunk_overlap_tokens' => 64,
    ]);
}

test('未单独配置的应用按代码默认解析引擎配置', function () {
    // 出厂默认（未 seed 覆盖）：向量检索关闭，KNOWLEDGE_DEFAULT_VECTOR_INDEX_ENABLED 未设时为 false
    expect(KnowledgeEngineConfigData::default()->vector_index_enabled)->toBeFalse();

    seedKnowledgeDefaults();
    $app = createSystemSettings();

    $engine = app(KnowledgeEngine::class)->current();

    expect($engine->vectorEnabled())->toBeTrue()
        ->and($engine->raptorEnabled())->toBeFalse()
        ->and($engine->embeddingDimension())->toBe(1536)
        ->and($engine->chunkingStrategy())->toBe(KnowledgeChunkingStrategy::Fixed)
        ->and($engine->chunkMaxTokens())->toBe(512)
        ->and($engine->chunkOverlapTokens())->toBe(64);
});

test('应用自身完整配置生效，与代码默认无关', function () {
    seedKnowledgeDefaults();
    $app = createSystemSettings();
    setInstanceKnowledgeConfig($app, [
        'raptor_index_enabled' => true,
        'chunk_max_tokens' => 1024,
        'embedding_dimension' => 3072,
    ]);

    $engine = app(KnowledgeEngine::class)->current();

    expect($engine->raptorEnabled())->toBeTrue()
        ->and($engine->chunkMaxTokens())->toBe(1024)
        ->and($engine->embeddingDimension())->toBe(3072)
        // setInstanceKnowledgeConfig 在默认之上合并，未传字段取默认值
        ->and($engine->vectorEnabled())->toBeTrue()
        ->and($engine->chunkOverlapTokens())->toBe(64);
});

test('应用配置了向量模型时按自身 model_id 解析备份', function () {
    seedKnowledgeDefaults();

    AiModelFactory::new()->forProvider(AiProviderFactory::new()->create())->embedding()
        ->state(['model_id' => 'default-embed'])->create();
    AiModelFactory::new()->forProvider(AiProviderFactory::new()->create())->embedding()
        ->state(['model_id' => 'ws-embed'])->create();

    $configured = createSystemSettings();
    setInstanceKnowledgeConfig($configured, [
        'embedding_model_id' => 'ws-embed',
        'embedding_dimension' => 3072,
    ]);

    $engine = app(KnowledgeEngine::class)->current();

    expect($engine->pinnedEmbeddingModel()?->model_id)->toBe('ws-embed')
        ->and($engine->embeddingDimension())->toBe(3072);
});

test('代码默认未 pin 模型时应用无可用向量模型', function () {
    config()->set('knowledge.defaults.embedding_model_id', null);
    $app = createSystemSettings();

    $engine = app(KnowledgeEngine::class)->current();

    expect($engine->pinnedEmbeddingModel())->toBeNull()
        ->and($engine->hasPinnedEmbeddingModel())->toBeFalse();
});
