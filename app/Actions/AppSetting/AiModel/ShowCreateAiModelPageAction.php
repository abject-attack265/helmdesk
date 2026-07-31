<?php

namespace App\Actions\AppSetting\AiModel;

use App\Data\AiModel\AiProviderOptionData;
use App\Data\AiModel\CatalogModelOptionData;
use App\Data\AiModel\ShowCreateAiModelPagePropsData;
use App\Data\EnumOptionData;
use App\Enums\AiModelPurpose;
use App\Models\AiProvider;
use App\Services\AiProvider\AiProviderCatalog;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染应用设置中的「新增 AI 模型」页：供应商选项、用途选项与品牌预设模型目录。
 */
class ShowCreateAiModelPageAction
{
    use AsAction;

    public function __construct(
        private readonly AiProviderCatalog $catalog,
    ) {}

    /**
     * 组装添加模型所需的选项。
     */
    public function handle(): ShowCreateAiModelPagePropsData
    {
        $providerOptions = AiProvider::query()
            ->orderBy('name')
            ->get()
            ->map(fn (AiProvider $provider) => AiProviderOptionData::fromModel($provider))
            ->all();

        $defaultModelsByBrand = [];
        foreach ($this->catalog->brandOptions() as $brandOption) {
            $brand = (string) $brandOption['brand'];
            $defaultModelsByBrand[$brand] = array_map(
                static fn (array $spec) => CatalogModelOptionData::fromCatalogSpec($spec),
                $this->catalog->defaultModelsForBrand($brand),
            );
        }

        return new ShowCreateAiModelPagePropsData(
            provider_options: $providerOptions,
            purpose_options: EnumOptionData::fromCases(AiModelPurpose::cases()),
            default_models_by_brand: $defaultModelsByBrand,
        );
    }

    /**
     * 渲染新增模型页。
     */
    public function asController(): Response
    {
        return Inertia::render('appSettings/aiModel/Create', $this->handle()->toArray());
    }
}
