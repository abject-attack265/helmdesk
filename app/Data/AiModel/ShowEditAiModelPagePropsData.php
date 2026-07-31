<?php

namespace App\Data\AiModel;

use Spatie\LaravelData\Data;

/**
 * 应用设置「编辑 AI 模型」页 props。
 *
 * 由 ShowEditAiModelPageAction 返回，传给 resources/js/pages/appSettings/aiModel/Edit.vue：
 * model 为当前模型（供应商 / 用途 / model_id 只读展示），仅可改名称与启用状态。
 */
class ShowEditAiModelPagePropsData extends Data
{
    public function __construct(
        public AiModelListItemData $model,
    ) {}
}
