<?php

namespace App\Actions\AppSetting\TranslationProvider;

use App\Actions\AppSetting\BuildProviderCredentialsAction;
use App\Data\Translation\FormCreateTranslationProviderData;
use App\Models\TranslationProvider;
use App\Services\Translation\TranslationProviderCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/** 创建翻译供应商。 */
class CreateTranslationProviderAction
{
    use AsAction;

    /** 注入翻译供应商目录。 */
    public function __construct(
        private readonly TranslationProviderCatalog $catalog,
    ) {}

    /**
     * 根据协议定义保存供应商和凭据。
     */
    public function handle(FormCreateTranslationProviderData $data): TranslationProvider
    {
        $defaultConfiguration = $this->catalog->defaultConfigurationForProtocol($data->protocol);
        $credentialFields = $this->catalog->credentialFieldsForProtocol($data->protocol);
        $credentials = BuildProviderCredentialsAction::run($credentialFields, [
            ...$defaultConfiguration,
            ...$data->configuration,
        ]);

        return TranslationProvider::query()->create([
            'slug' => Str::slug($data->name).'-'.Str::lower(Str::random(6)),
            'name' => $data->name,
            'protocol' => $data->protocol,
            'icon' => $this->catalog->iconForProtocol($data->protocol),
            'credentials' => filled($credentials) ? $credentials : null,
            'credential_fields' => $credentialFields,
            'is_active' => $data->is_active,
        ]);
    }

    /**
     * 校验创建表单并返回供应商列表页。
     */
    public function asController(Request $request): RedirectResponse
    {
        $data = FormCreateTranslationProviderData::from($request);
        $this->handle($data);

        return redirect()->route('app.manage.translation-providers.index');
    }
}
