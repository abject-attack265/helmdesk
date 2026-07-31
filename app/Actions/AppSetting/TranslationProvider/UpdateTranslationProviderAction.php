<?php

namespace App\Actions\AppSetting\TranslationProvider;

use App\Data\Translation\FormUpdateTranslationProviderData;
use App\Models\TranslationProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/** 更新翻译供应商的名称、凭据和启用状态。 */
class UpdateTranslationProviderAction
{
    use AsAction;

    /**
     * 合并凭据并保存名称与启用状态。
     */
    public function handle(string $id, FormUpdateTranslationProviderData $data): TranslationProvider
    {
        $provider = TranslationProvider::query()->findOrFail($id);

        $credentials = $provider->mergeCredentials($data->configuration);

        $provider->name = $data->name;
        $provider->is_active = $data->is_active;
        $provider->credentials = filled($credentials) ? $credentials : null;
        $provider->save();

        return $provider;
    }

    /**
     * 校验更新表单并返回供应商列表页。
     */
    public function asController(Request $request, string $id): RedirectResponse
    {
        $data = FormUpdateTranslationProviderData::from($request);
        $this->handle($id, $data);

        return redirect()->route('app.manage.translation-providers.index');
    }
}
