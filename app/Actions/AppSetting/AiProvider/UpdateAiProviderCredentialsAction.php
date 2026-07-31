<?php

namespace App\Actions\AppSetting\AiProvider;

use App\Data\AiProvider\FormUpdateAiProviderCredentialsData;
use App\Models\AiProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新应用 AI 供应商的名称与凭据。
 *
 * 编辑表单回显全部凭据字段，提交空值即清空对应字段。
 */
class UpdateAiProviderCredentialsAction
{
    use AsAction;

    /**
     * 合并凭据并保存名称。
     */
    public function handle(string $providerId, FormUpdateAiProviderCredentialsData $data): AiProvider
    {
        $provider = AiProvider::query()->findOrFail($providerId);

        $credentials = $provider->mergeCredentials($data->configuration);

        $provider->name = $data->name;
        $provider->credentials = filled($credentials) ? $credentials : null;
        $provider->save();

        return $provider;
    }

    /**
     * 从请求取表单数据并保存后返回列表页。
     */
    public function asController(Request $request, string $provider): RedirectResponse
    {
        $data = FormUpdateAiProviderCredentialsData::from($request);
        $this->handle($provider, $data);

        return redirect()->route('app.manage.ai-providers.index');
    }
}
