<?php

namespace App\Data\AiModel;

use App\Data\EnumOptionData;
use Spatie\LaravelData\Data;

/**
 * 应用设置「AI 模型管理」列表页 props。
 *
 * 由 ShowAiModelListAction 返回，传给 resources/js/pages/appSettings/aiModel/List.vue：models 跨供应商全量，
 * 前端按 purpose 分 Tab 展示；purpose_tabs 为全部用途（value+label），决定 Tab 的顺序与文案。
 */
class ShowAiModelListPagePropsData extends Data
{
    public function __construct(
        /** @var AiModelListItemData[] */
        public array $models,

        /** @var EnumOptionData[] */
        public array $purpose_tabs,
    ) {}
}
