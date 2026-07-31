<?php

namespace App\Data\AiProvider;

use Spatie\LaravelData\Data;

/**
 * 应用设置「新增 AI 供应商」页 props。
 *
 * 由 ShowCreateAiProviderPageAction 返回，传给 resources/js/pages/appSettings/aiProvider/Create.vue：
 * brand_options 含各品牌的图标与凭据字段，供前端按所选品牌动态渲染凭据表单。
 */
class ShowCreateAiProviderPagePropsData extends Data
{
    public function __construct(
        /** @var BrandOptionData[] */
        public array $brand_options,
    ) {}
}
