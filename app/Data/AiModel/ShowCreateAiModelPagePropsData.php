<?php

namespace App\Data\AiModel;

use App\Data\EnumOptionData;
use Spatie\LaravelData\Data;

/**
 * 应用设置「新增 AI 模型」页 props。
 *
 * 由 ShowCreateAiModelPageAction 返回，传给 resources/js/pages/appSettings/aiModel/Create.vue：
 * provider_options 供选供应商；purpose_options 供选用途（type 由用途派生）；
 * default_models_by_brand 供「预设模型」弹窗按品牌+类型筛选一键填 model_id。
 */
class ShowCreateAiModelPagePropsData extends Data
{
    public function __construct(
        /** @var AiProviderOptionData[] */
        public array $provider_options,

        /** @var EnumOptionData[] */
        public array $purpose_options,

        /** @var array<string, CatalogModelOptionData[]> */
        public array $default_models_by_brand,
    ) {}
}
