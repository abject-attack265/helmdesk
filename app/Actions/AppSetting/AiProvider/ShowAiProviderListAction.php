<?php

namespace App\Actions\AppSetting\AiProvider;

use App\Data\AiProvider\AiProviderData;
use App\Data\AiProvider\ShowAiProviderListPagePropsData;
use App\Models\AiProvider;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 查询应用设置中的 AI 供应商列表。
 *
 * 表格展示全部供应商及凭据完整度；模型在「AI 模型管理」页单独维护。
 */
class ShowAiProviderListAction
{
    use AsAction;

    /**
     * 组装供应商列表。
     */
    public function handle(): ShowAiProviderListPagePropsData
    {
        $providers = AiProvider::query()
            ->orderBy('name')
            ->get()
            ->map(fn (AiProvider $provider) => AiProviderData::fromModel($provider))
            ->all();

        return new ShowAiProviderListPagePropsData(providers: $providers);
    }

    /**
     * 渲染 AI 供应商列表页。
     */
    public function asController(): Response
    {
        return Inertia::render('appSettings/aiProvider/List', $this->handle()->toArray());
    }
}
