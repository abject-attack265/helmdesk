<?php

namespace App\Data\AiModel;

use Spatie\LaravelData\Data;

/**
 * 「AI 模型管理」更新模型表单数据（来源：resources/js/pages/appSettings/aiModel/ModelForm.vue）。
 *
 * 仅改名称、启用状态、权重与媒体输入能力；供应商 / 用途 / model_id 创建后不可变。
 */
class FormUpdateAiModelData extends Data
{
    /**
     * 接收应用后台编辑模型表单字段。
     */
    public function __construct(
        public string $name,
        public bool $is_active,
        public int $weight,
        public bool $supports_image_input,
        public bool $supports_video_input,
    ) {}

    /**
     * 校验模型的可变字段。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'is_active' => ['required', 'boolean'],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
            'supports_image_input' => ['required', 'boolean'],
            'supports_video_input' => ['required', 'boolean'],
        ];
    }
}
