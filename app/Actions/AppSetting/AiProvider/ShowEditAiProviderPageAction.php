<?php

namespace App\Actions\AppSetting\AiProvider;

use App\Data\AiProvider\AiProviderData;
use App\Data\AiProvider\ShowEditAiProviderPagePropsData;
use App\Models\AiProvider;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染应用设置「编辑 AI 供应商」页：下发脱敏后的供应商凭据字段与遮掩值。
 */
class ShowEditAiProviderPageAction
{
    use AsAction;

    /**
     * 加载供应商并脱敏后下发。
     */
    public function handle(string $providerId): ShowEditAiProviderPagePropsData
    {
        $provider = AiProvider::query()->findOrFail($providerId);

        return new ShowEditAiProviderPagePropsData(provider: AiProviderData::fromModel($provider));
    }

    /**
     * 渲染编辑页。
     */
    public function asController(string $provider): Response
    {
        return Inertia::render('appSettings/aiProvider/Edit', $this->handle($provider)->toArray());
    }
}
