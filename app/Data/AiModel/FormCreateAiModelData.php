<?php

namespace App\Data\AiModel;

use App\Enums\AiModelPurpose;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 「AI 模型管理」新增模型表单数据（来源：resources/js/pages/appSettings/aiModel/ModelForm.vue）。
 *
 * 一行=一个模型+一个用途：选供应商 + 用途 + model_id + 名称 + 权重及媒体输入能力；type 由用途派生。
 */
class FormCreateAiModelData extends Data
{
    /**
     * 接收应用后台新增模型表单字段。
     */
    public function __construct(
        public string $ai_provider_id,
        public AiModelPurpose $purpose,
        public string $model_id,
        public string $name,
        public int $weight,
        public bool $supports_image_input,
        public bool $supports_video_input,
    ) {}

    /**
     * 校验新增模型的用途、权重与媒体能力字段。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'ai_provider_id' => ['required', 'string', 'exists:ai_providers,id'],
            'purpose' => ['required', Rule::enum(AiModelPurpose::class)],
            'model_id' => ['required', 'string', 'max:128'],
            'name' => ['required', 'string', 'max:128'],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
            'supports_image_input' => ['required', 'boolean'],
            'supports_video_input' => ['required', 'boolean'],
        ];
    }
}
