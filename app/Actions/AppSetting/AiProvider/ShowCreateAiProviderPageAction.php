<?php

namespace App\Actions\AppSetting\AiProvider;

use App\Data\AiProvider\BrandOptionData;
use App\Data\AiProvider\ShowCreateAiProviderPagePropsData;
use App\Services\AiProvider\AiProviderCatalog;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染应用设置「新增 AI 供应商」页：给出品牌目录（含图标与各品牌凭据字段）。
 */
class ShowCreateAiProviderPageAction
{
    use AsAction;

    public function __construct(
        private readonly AiProviderCatalog $catalog,
    ) {}

    /**
     * 组装品牌目录选项。
     */
    public function handle(): ShowCreateAiProviderPagePropsData
    {
        $brandOptions = array_map(
            static fn (array $option) => BrandOptionData::from($option),
            $this->catalog->brandOptions(),
        );

        return new ShowCreateAiProviderPagePropsData(brand_options: $brandOptions);
    }

    /**
     * 渲染新增页。
     */
    public function asController(): Response
    {
        return Inertia::render('appSettings/aiProvider/Create', $this->handle()->toArray());
    }
}
