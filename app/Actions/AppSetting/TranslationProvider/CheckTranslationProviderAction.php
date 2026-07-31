<?php

namespace App\Actions\AppSetting\TranslationProvider;

use App\Data\Translation\FormCheckTranslationProviderData;
use App\Data\Translation\TranslationCheckResultData;
use App\Enums\TranslationProviderType;
use App\Models\TranslationProvider;
use App\Services\Translation\Exceptions\TranslationException;
use App\Services\Translation\TranslationProviderCatalog;
use App\Services\Translation\TranslatorManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 测试已保存或未保存的翻译供应商配置。
 */
class CheckTranslationProviderAction
{
    use AsAction;

    /** 注入翻译驱动管理器和供应商目录。 */
    public function __construct(
        private readonly TranslatorManager $manager,
        private readonly TranslationProviderCatalog $catalog,
    ) {}

    /**
     * 使用可选的未保存凭据测试已保存供应商。
     *
     * @param  array<string, mixed>|null  $configuration
     */
    public function handle(
        string $providerId,
        FormCheckTranslationProviderData $data,
        ?array $configuration = null,
    ): TranslationCheckResultData {
        $provider = TranslationProvider::query()->findOrFail($providerId);

        $hasOverride = $configuration !== null && $configuration !== [];
        if ($hasOverride) {
            $provider->credentials = $provider->mergeCredentials($configuration);
        }

        return $this->runCheck($provider, $data, fresh: $hasOverride);
    }

    /**
     * 校验测试请求并返回供应商连接结果。
     */
    public function asController(Request $request, ?string $id = null): JsonResponse
    {
        $data = FormCheckTranslationProviderData::from($request);
        $configuration = $request->array('configuration');

        if ($id === null) {
            return response()->json($this->handleDraft(
                $data,
                $configuration,
                $request,
            )->toArray());
        }

        return response()->json($this->handle(
            $id,
            $data,
            $configuration === [] ? null : $configuration,
        )->toArray());
    }

    /**
     * 构造未保存的供应商并执行连接测试。
     *
     * @param  array<string, mixed>  $configuration
     */
    private function handleDraft(
        FormCheckTranslationProviderData $data,
        array $configuration,
        Request $request,
    ): TranslationCheckResultData {
        $validated = $request->validate([
            'protocol' => ['required', Rule::enum(TranslationProviderType::class)],
        ]);

        $protocol = TranslationProviderType::from((string) $validated['protocol']);
        $provider = new TranslationProvider([
            'slug' => 'draft',
            'name' => 'Draft',
            'protocol' => $protocol,
            'icon' => $this->catalog->iconForProtocol($protocol),
            'credentials' => $configuration,
            'credential_fields' => $this->catalog->credentialFieldsForProtocol($protocol),
            'is_active' => true,
        ]);
        $provider->id = 'draft';

        return $this->runCheck($provider, $data, fresh: true);
    }

    /**
     * 执行翻译连接测试并返回结果。
     */
    private function runCheck(
        TranslationProvider $provider,
        FormCheckTranslationProviderData $data,
        bool $fresh,
    ): TranslationCheckResultData {
        try {
            $driver = $this->manager->driverFor($provider, fresh: $fresh);
            $result = $driver->translate($data->text, $data->source_lang ?? 'auto', $data->target_lang);

            return new TranslationCheckResultData(
                success: true,
                message: __('translation.check_succeeded'),
                result: $result,
            );
        } catch (TranslationException $exception) {
            return new TranslationCheckResultData(
                success: false,
                message: $exception->getMessage(),
            );
        }
    }
}
