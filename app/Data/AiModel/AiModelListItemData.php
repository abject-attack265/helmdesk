<?php

namespace App\Data\AiModel;

use App\Enums\AiModelPurpose;
use App\Enums\AiModelType;
use App\Models\AiModel;
use Spatie\LaravelData\Data;

/**
 * AI 模型管理页的模型列表项（一行=一个模型+一个用途）。
 *
 * 由 ShowAiModelListAction 组装，传给 resources/js/pages/appSettings/aiModel/List.vue 展示用途、权重与媒体能力。
 */
class AiModelListItemData extends Data
{
    /**
     * 承载模型列表展示字段。
     */
    public function __construct(
        public string $id,
        public string $model_id,
        public string $name,
        public AiModelType $type,
        public string $type_label,
        public AiModelPurpose $purpose,
        public string $purpose_label,
        public string $ai_provider_id,
        public string $provider_name,
        public bool $is_active,
        public int $weight,
        public bool $supports_image_input,
        public bool $supports_video_input,
    ) {}

    /**
     * 从模型（需预载 provider）构造列表项。
     */
    public static function fromModel(AiModel $model): self
    {
        $type = $model->type instanceof AiModelType ? $model->type : AiModelType::from((string) $model->type);
        $purpose = $model->purpose;

        return new self(
            id: $model->id,
            model_id: $model->model_id,
            name: $model->name,
            type: $type,
            type_label: $type->label(),
            purpose: $purpose,
            purpose_label: $purpose->label(),
            ai_provider_id: $model->ai_provider_id,
            provider_name: $model->provider->name,
            is_active: $model->is_active,
            weight: $model->weight,
            supports_image_input: $model->supports_image_input,
            supports_video_input: $model->supports_video_input,
        );
    }
}
