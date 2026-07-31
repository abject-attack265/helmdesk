<?php

namespace App\Actions\AppSetting\TranslationProvider;

use App\Data\Translation\ShowEditTranslationProviderPagePropsData;
use App\Data\Translation\TranslationProviderData;
use App\Models\TranslationProvider;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/** 展示翻译供应商编辑页。 */
class ShowEditTranslationProviderPageAction
{
    use AsAction;

    /**
     * 组装编辑页的翻译供应商数据。
     */
    public function handle(string $id): ShowEditTranslationProviderPagePropsData
    {
        $provider = TranslationProvider::query()->findOrFail($id);

        return new ShowEditTranslationProviderPagePropsData(
            provider: TranslationProviderData::fromModel($provider),
        );
    }

    /**
     * 返回翻译供应商编辑页。
     */
    public function asController(string $id): Response
    {
        return Inertia::render('appSettings/translationProvider/Edit', $this->handle($id)->toArray());
    }
}
