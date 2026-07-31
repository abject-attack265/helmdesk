<?php

namespace App\Actions\AppSetting\AiModel;

use App\Data\AiModel\AiModelListItemData;
use App\Data\AiModel\ShowEditAiModelPagePropsData;
use App\Models\AiModel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染应用设置中的「编辑 AI 模型」页：下发当前模型。
 */
class ShowEditAiModelPageAction
{
    use AsAction;

    /**
     * 加载模型。
     */
    public function handle(string $modelId): ShowEditAiModelPagePropsData
    {
        $model = AiModel::query()->with('provider')->findOrFail($modelId);

        return new ShowEditAiModelPagePropsData(
            model: AiModelListItemData::fromModel($model),
        );
    }

    /**
     * 渲染编辑模型页。
     */
    public function asController(string $model): Response
    {
        return Inertia::render('appSettings/aiModel/Edit', $this->handle($model)->toArray());
    }
}
