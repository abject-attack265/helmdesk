<?php

namespace App\Actions\AppSetting\AiModel;

use App\Data\AiModel\FormCreateAiModelData;
use App\Exceptions\BusinessException;
use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 新增 AI 模型（一行=一个模型+一个用途）：选供应商 + 用途 + model_id + 名称、权重与媒体输入能力。
 * type 由用途的能力类型派生；weight 决定同用途内加权随机选主的概率。
 */
class CreateAiModelAction
{
    use AsAction;

    /**
     * 在指定供应商下写入模型。
     */
    public function handle(FormCreateAiModelData $data): AiModel
    {
        $provider = AiProvider::query()->findOrFail($data->ai_provider_id);

        $duplicate = AiModel::query()
            ->where('ai_provider_id', $provider->id)
            ->where('model_id', $data->model_id)
            ->where('purpose', $data->purpose->value)
            ->exists();
        if ($duplicate) {
            throw new BusinessException(__('ai.model_purpose_exists'));
        }

        return $provider->models()->create([
            'model_id' => $data->model_id,
            'name' => $data->name,
            'type' => $data->purpose->modelType()->value,
            'purpose' => $data->purpose->value,
            'is_active' => true,
            'weight' => $data->weight,
            'supports_image_input' => $data->supports_image_input,
            'supports_video_input' => $data->supports_video_input,
        ]);
    }

    /**
     * 校验表单并落库后返回模型列表页。
     */
    public function asController(Request $request): RedirectResponse
    {
        $this->handle(FormCreateAiModelData::from($request));

        return redirect()->route('app.manage.ai-models.index');
    }
}
