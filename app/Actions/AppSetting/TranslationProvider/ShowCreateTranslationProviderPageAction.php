<?php

namespace App\Actions\AppSetting\TranslationProvider;

use App\Data\CredentialFieldData;
use App\Data\EnumOptionData;
use App\Data\Translation\ShowCreateTranslationProviderPagePropsData;
use App\Enums\TranslationProviderType;
use App\Services\Translation\TranslationProviderCatalog;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/** 展示翻译供应商创建页。 */
class ShowCreateTranslationProviderPageAction
{
    use AsAction;

    /**
     * 注入翻译供应商协议目录。
     */
    public function __construct(
        private readonly TranslationProviderCatalog $catalog,
    ) {}

    /**
     * 组装创建页的协议选项、图标与凭据字段。
     */
    public function handle(): ShowCreateTranslationProviderPagePropsData
    {
        return new ShowCreateTranslationProviderPagePropsData(
            protocol_options: EnumOptionData::fromCases(TranslationProviderType::cases()),
            protocol_credential_fields: $this->credentialFieldsByProtocol(),
            protocol_icons: $this->iconsByProtocol(),
        );
    }

    /**
     * 返回翻译供应商创建页。
     */
    public function asController(): Response
    {
        return Inertia::render('appSettings/translationProvider/Create', $this->handle()->toArray());
    }

    /**
     * 按协议组装凭据字段数据。
     *
     * @return array<string, CredentialFieldData[]>
     */
    private function credentialFieldsByProtocol(): array
    {
        $fields = [];

        foreach (TranslationProviderType::cases() as $protocol) {
            $fields[$protocol->value] = array_map(
                static fn (array $field): CredentialFieldData => CredentialFieldData::from($field),
                $this->catalog->credentialFieldsForProtocol($protocol),
            );
        }

        return $fields;
    }

    /**
     * 按协议组装图标标识。
     *
     * @return array<string, string>
     */
    private function iconsByProtocol(): array
    {
        $icons = [];

        foreach (TranslationProviderType::cases() as $protocol) {
            $icons[$protocol->value] = $this->catalog->iconForProtocol($protocol);
        }

        return $icons;
    }
}
