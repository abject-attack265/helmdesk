<?php

namespace Database\Factories;

use App\Enums\AiModelPurpose;
use App\Enums\AiModelType;
use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 当前应用的 AI 模型工厂（一行对应一个模型和一个用途）。
 *
 * 默认生成启用中的接待 LLM，不声明图片或视频输入能力。
 *
 * @extends Factory<AiModel>
 */
class AiModelFactory extends Factory
{
    /**
     * @var class-string<AiModel>
     */
    protected $model = AiModel::class;

    /**
     * 生成默认模型字段。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_provider_id' => fn (): string => AiProviderFactory::new()->create()->id,
            'model_id' => 'model-'.Str::lower(Str::random(8)),
            'name' => 'Model '.Str::upper(Str::random(6)),
            'type' => AiModelType::Llm->value,
            'purpose' => AiModelPurpose::ReceptionChat->value,
            'is_active' => true,
            'weight' => 1,
            'supports_image_input' => false,
            'supports_video_input' => false,
        ];
    }

    /**
     * 指定所属供应商。
     */
    public function forProvider(AiProvider $provider): self
    {
        return $this->state(fn (): array => ['ai_provider_id' => $provider->id]);
    }

    /**
     * 设定用途（type 随之取该用途的能力类型）。
     */
    public function purpose(AiModelPurpose $purpose): self
    {
        return $this->state(fn (): array => [
            'purpose' => $purpose->value,
            'type' => $purpose->modelType()->value,
        ]);
    }

    /**
     * 切换为 embedding 模型（用途=向量检索）。
     */
    public function embedding(): self
    {
        return $this->state(fn (): array => [
            'type' => AiModelType::Embedding->value,
            'purpose' => AiModelPurpose::Embedding->value,
        ]);
    }

    /**
     * 切换为 rerank 模型（用途=重排序）。
     */
    public function rerank(): self
    {
        return $this->state(fn (): array => [
            'type' => AiModelType::Rerank->value,
            'purpose' => AiModelPurpose::Rerank->value,
        ]);
    }

    /**
     * 标记为停用（不参与运行时取用）。
     */
    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
