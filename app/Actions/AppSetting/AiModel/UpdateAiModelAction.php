<?php

namespace App\Actions\AppSetting\AiModel;

use App\Data\AiModel\FormUpdateAiModelData;
use App\Models\AiModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新 AI 模型的名称、启用状态、权重与媒体输入能力；供应商 / 用途 / model_id 创建后不可变。
 */
class UpdateAiModelAction
{
    use AsAction;

    /**
     * 保存模型可变字段。
     */
    public function handle(string $modelId, FormUpdateAiModelData $data): AiModel
    {
        $model = AiModel::query()->findOrFail($modelId);

        $model->name = $data->name;
        $model->is_active = $data->is_active;
        $model->weight = $data->weight;
        $model->supports_image_input = $data->supports_image_input;
        $model->supports_video_input = $data->supports_video_input;
        $model->save();

        return $model;
    }

    /**
     * 从请求取表单数据并保存后返回模型列表页。
     */
    public function asController(Request $request, string $model): RedirectResponse
    {
        $this->handle($model, FormUpdateAiModelData::from($request));

        return redirect()->route('app.manage.ai-models.index');
    }
}
