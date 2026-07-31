<?php

namespace App\Actions\AppSetting\AiModel;

use App\Data\AiModel\AiModelListItemData;
use App\Data\AiModel\ShowAiModelListPagePropsData;
use App\Data\EnumOptionData;
use App\Enums\AiModelPurpose;
use App\Models\AiModel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染应用设置中的「AI 模型管理」列表页：按 purpose 分 Tab、Tab 内按 weight 降序展示。
 */
class ShowAiModelListAction
{
    use AsAction;

    /**
     * 组装模型列表与用途 Tab。
     */
    public function handle(): ShowAiModelListPagePropsData
    {
        $models = AiModel::query()
            ->with('provider')
            ->orderBy('purpose')
            ->orderByDesc('weight')
            ->orderBy('id')
            ->get()
            ->map(fn (AiModel $model) => AiModelListItemData::fromModel($model))
            ->all();

        return new ShowAiModelListPagePropsData(
            models: $models,
            purpose_tabs: EnumOptionData::fromCases(AiModelPurpose::cases()),
        );
    }

    /**
     * 渲染 AI 模型管理列表页。
     */
    public function asController(): Response
    {
        return Inertia::render('appSettings/aiModel/List', $this->handle()->toArray());
    }
}
