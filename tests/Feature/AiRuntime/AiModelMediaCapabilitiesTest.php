<?php

use App\Actions\AppSetting\AiModel\CreateAiModelAction;
use App\Actions\AppSetting\AiModel\UpdateAiModelAction;
use App\Data\AiModel\FormCreateAiModelData;
use App\Data\AiModel\FormUpdateAiModelData;
use App\Enums\AiModelPurpose;
use Database\Factories\AiModelFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('模型媒体能力默认关闭并可通过模型管理动作更新', function () {
    $defaultModel = AiModelFactory::new()->create();

    expect($defaultModel->supports_image_input)->toBeFalse()
        ->and($defaultModel->supports_video_input)->toBeFalse();

    $provider = makeUsableAiProvider();
    $model = CreateAiModelAction::run(new FormCreateAiModelData(
        ai_provider_id: $provider->id,
        purpose: AiModelPurpose::Assistant,
        model_id: 'media-model',
        name: 'Media Model',
        weight: 10,
        supports_image_input: true,
        supports_video_input: false,
    ));

    expect($model->supports_image_input)->toBeTrue()
        ->and($model->supports_video_input)->toBeFalse();

    $updated = UpdateAiModelAction::run($model->id, new FormUpdateAiModelData(
        name: 'Media Model',
        is_active: true,
        weight: 10,
        supports_image_input: false,
        supports_video_input: true,
    ));

    expect($updated->supports_image_input)->toBeFalse()
        ->and($updated->supports_video_input)->toBeTrue();
});
