<?php

namespace App\Actions\AppSetting\AiProvider;

use App\Actions\AppSetting\BuildProviderCredentialsAction;
use App\Data\AiProvider\FormCreateAiProviderData;
use App\Models\AiProvider;
use App\Services\AiProvider\AiProviderCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 按品牌创建应用 AI 供应商（纯凭据）。
 *
 * 只落一条供应商记录，不自动创建模型；模型在「AI 模型管理」页单独添加。
 */
class CreateAiProviderAction
{
    use AsAction;

    /** 注入 AI 供应商品牌目录。 */
    public function __construct(
        private readonly AiProviderCatalog $catalog,
    ) {}

    /**
     * 按品牌拼装协议/图标/凭据字段并落库。
     */
    public function handle(FormCreateAiProviderData $data): AiProvider
    {
        $brand = $data->brand;
        $credentialFields = $this->catalog->credentialFieldsForBrand($brand);
        $credentials = BuildProviderCredentialsAction::run($credentialFields, [
            ...$this->catalog->defaultConfigurationForBrand($brand),
            ...$data->configuration,
        ]);

        return AiProvider::query()->create([
            'brand' => $brand,
            'slug' => Str::slug($data->name).'-'.Str::lower(Str::random(6)),
            'name' => $data->name,
            'protocol' => $this->catalog->protocolForBrand($brand),
            'icon' => $this->catalog->iconForBrand($brand),
            'credentials' => filled($credentials) ? $credentials : null,
            'credential_fields' => $credentialFields,
        ]);
    }

    /**
     * 校验表单并落库后回到列表页。
     */
    public function asController(Request $request): RedirectResponse
    {
        $this->handle(FormCreateAiProviderData::from($request));

        return redirect()->route('app.manage.ai-providers.index');
    }
}
