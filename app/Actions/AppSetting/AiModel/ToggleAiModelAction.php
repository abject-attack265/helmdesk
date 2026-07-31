<?php

namespace App\Actions\AppSetting\AiModel;

use App\Models\AiModel;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 切换 AI 模型的启用状态（列表内联 Switch）；停用即不参与运行时取用。
 */
class ToggleAiModelAction
{
    use AsAction;

    public function handle(string $modelId): AiModel
    {
        $model = AiModel::query()->findOrFail($modelId);
        $model->is_active = ! $model->is_active;
        $model->save();

        return $model;
    }

    public function asController(string $model): RedirectResponse
    {
        $this->handle($model);

        return back();
    }
}
