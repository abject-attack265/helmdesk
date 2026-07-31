<?php

use App\Enums\AiModelPurpose;
use App\Services\AiRuntime\AiModelPool;
use Database\Factories\AiProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('modelsForPurpose 只含启用且凭据完整且被指派该用途的模型', function () {
    $provider = makeUsableAiProvider();

    $usable = makeAiModel(provider: $provider, purpose: AiModelPurpose::BackgroundTask);
    $inactive = makeAiModel(provider: $provider, purpose: AiModelPurpose::BackgroundTask, isActive: false);
    $wrongPurpose = makeAiModel(provider: $provider, purpose: AiModelPurpose::ReceptionChat);

    $missingCredentialsProvider = AiProviderFactory::new()->withoutCredentials()->create();
    $missingCredentials = makeAiModel(provider: $missingCredentialsProvider, purpose: AiModelPurpose::BackgroundTask);

    $pool = app(AiModelPool::class);
    $ids = $pool->modelsForPurpose(AiModelPurpose::BackgroundTask)->pluck('id')->all();

    expect($ids)->toContain($usable->id)
        ->and($ids)->not->toContain($inactive->id)
        ->and($ids)->not->toContain($wrongPurpose->id)
        ->and($ids)->not->toContain($missingCredentials->id);
});

it('modelsForPurpose 返回全部可用模型，并按 weight 加权随机选主', function () {
    $provider = makeUsableAiProvider();

    $heavy = makeAiModel(provider: $provider, purpose: AiModelPurpose::Assistant, weight: 100);
    $light = makeAiModel(provider: $provider, purpose: AiModelPurpose::Assistant, weight: 1);

    $pool = app(AiModelPool::class);

    // 池里始终是全部可用模型，顺序随机但集合恒定。
    $expected = collect([$heavy->id, $light->id])->sort()->values()->all();
    expect($pool->modelsForPurpose(AiModelPurpose::Assistant)->pluck('id')->sort()->values()->all())
        ->toBe($expected);

    // 高权重模型在多次抽样中被选为主模型的次数显著更多。
    $heavyFirst = 0;
    foreach (range(1, 200) as $ignored) {
        if ($pool->firstForPurpose(AiModelPurpose::Assistant)?->id === $heavy->id) {
            $heavyFirst++;
        }
    }

    expect($heavyFirst)->toBeGreaterThan(150);
});

it('单一可用模型时 firstForPurpose 恒定返回该模型', function () {
    $only = makeAiModel(purpose: AiModelPurpose::Assistant, weight: 5);

    $pool = app(AiModelPool::class);

    foreach (range(1, 5) as $ignored) {
        expect($pool->firstForPurpose(AiModelPurpose::Assistant)?->id)->toBe($only->id);
    }
});

it('hasUsable 在有可用模型时为真，否则为假', function () {
    $pool = app(AiModelPool::class);

    expect($pool->hasUsable(AiModelPurpose::Rerank))->toBeFalse();

    makeAiModel(purpose: AiModelPurpose::Rerank);

    expect($pool->hasUsable(AiModelPurpose::Rerank))->toBeTrue();
});

it('停用 / 缺凭据 / 无该用途的模型都不会让 hasUsable 为真', function () {
    makeAiModel(purpose: AiModelPurpose::BackgroundTask, isActive: false);

    $missingCredentialsProvider = AiProviderFactory::new()->withoutCredentials()->create();
    makeAiModel(provider: $missingCredentialsProvider, purpose: AiModelPurpose::BackgroundTask);

    makeAiModel(purpose: AiModelPurpose::ReceptionChat);

    expect(app(AiModelPool::class)->hasUsable(AiModelPurpose::BackgroundTask))->toBeFalse();
});
