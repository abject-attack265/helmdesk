<?php

use App\Enums\AiModelPurpose;
use App\Models\AiModel;
use App\Models\AiProvider;
use Database\Factories\AiModelFactory;
use Database\Factories\AiProviderFactory;

/**
 * AI 运行时测试助手：按用途 seed 全局可用的 AI 供应商与模型（一行=一个模型+一个用途）。
 *
 * 运行时从全局「启用且凭据完整且用途匹配」的模型池按 weight 加权随机取用。需要「AI 可用」的集成测试用这些助手快速 seed。
 */

/**
 * 建一个凭据完整的全局可用 AI 供应商。
 *
 * @param  array<string, mixed>  $attributes
 */
function makeUsableAiProvider(array $attributes = []): AiProvider
{
    return AiProviderFactory::new()->create($attributes);
}

/**
 * 在指定（或新建）供应商下建一个「单用途」模型；weight 默认 1，可指定以测试加权随机选择。
 */
function makeAiModel(
    AiModelPurpose $purpose = AiModelPurpose::ReceptionChat,
    ?AiProvider $provider = null,
    bool $isActive = true,
    int $weight = 1,
): AiModel {
    $provider ??= makeUsableAiProvider();

    return AiModelFactory::new()
        ->forProvider($provider)
        ->purpose($purpose)
        ->state([
            'is_active' => $isActive,
            'weight' => $weight,
        ])
        ->create();
}

/**
 * Seed 一套「AI 可用」的全局模型：一个凭据完整的供应商 + 各 LLM 用途各一个模型 + 一个 embedding 模型。
 *
 * @return array{provider: AiProvider, llm: AiModel, embedding: AiModel}
 */
function seedUsableAiModels(): array
{
    $provider = makeUsableAiProvider();

    $llm = makeAiModel(AiModelPurpose::ReceptionChat, $provider);
    makeAiModel(AiModelPurpose::BackgroundTask, $provider);
    makeAiModel(AiModelPurpose::Assistant, $provider);
    $embedding = makeAiModel(AiModelPurpose::Embedding, $provider);

    return ['provider' => $provider, 'llm' => $llm, 'embedding' => $embedding];
}
