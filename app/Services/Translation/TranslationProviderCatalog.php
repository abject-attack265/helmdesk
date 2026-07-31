<?php

namespace App\Services\Translation;

use App\Enums\TranslationProviderType;
use App\Services\Concerns\BuildsCredentialFieldDefinitions;

/**
 * 定义翻译供应商协议的凭据字段、图标和默认值。
 */
class TranslationProviderCatalog
{
    use BuildsCredentialFieldDefinitions;

    /**
     * 返回支持的翻译供应商定义表。
     *
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            TranslationProviderType::GoogleTranslate->value => [
                'icon' => 'google',
                'credential_fields' => [
                    $this->passwordField('api_key', 'API Key'),
                ],
            ],
            TranslationProviderType::DeepL->value => [
                'icon' => 'deepl',
                'credential_fields' => [
                    $this->passwordField('auth_key', 'Auth Key'),
                    $this->urlField('endpoint', 'Endpoint', required: true, default: 'https://api.deepl.com'),
                ],
            ],
            TranslationProviderType::AzureTranslator->value => [
                'icon' => 'azure',
                'credential_fields' => [
                    $this->passwordField('api_key', 'API Key'),
                    $this->textField('region', 'Region', required: false),
                    $this->urlField('endpoint', 'Endpoint', required: true, default: 'https://api.cognitive.microsofttranslator.com'),
                ],
            ],
            TranslationProviderType::BaiduTranslate->value => [
                'icon' => 'baidu',
                'credential_fields' => [
                    $this->textField('app_id', 'App ID'),
                    $this->passwordField('app_secret', 'App Secret'),
                    $this->urlField('endpoint', 'Endpoint', required: true, default: 'https://fanyi-api.baidu.com/api/trans/vip/translate'),
                ],
            ],
            TranslationProviderType::TencentCloudTranslate->value => [
                'icon' => 'tencent-cloud',
                'credential_fields' => [
                    $this->passwordField('secret_id', 'Secret ID'),
                    $this->passwordField('secret_key', 'Secret Key'),
                    $this->textField('region', 'Region', default: 'ap-guangzhou'),
                    $this->urlField('endpoint', 'Endpoint', required: true, default: 'https://tmt.tencentcloudapi.com'),
                ],
            ],
            TranslationProviderType::AmazonTranslate->value => [
                'icon' => 'aws',
                'credential_fields' => [
                    $this->passwordField('access_key_id', 'Access Key ID'),
                    $this->passwordField('secret_access_key', 'Secret Access Key'),
                    $this->passwordField('session_token', 'Session Token', required: false),
                    $this->textField('region', 'Region', default: 'us-east-1'),
                    $this->urlField('endpoint', 'Endpoint', required: false),
                ],
            ],
            TranslationProviderType::DeepSeek->value => [
                'icon' => 'deepseek',
                'credential_fields' => [
                    $this->passwordField('api_key', 'API Key'),
                ],
            ],
        ];
    }

    /**
     * 返回协议的凭据字段定义。
     *
     * @return array<int, array<string, mixed>>
     */
    public function credentialFieldsForProtocol(TranslationProviderType $protocol): array
    {
        return $this->definitions()[$protocol->value]['credential_fields'];
    }

    /**
     * 返回协议的图标标识。
     */
    public function iconForProtocol(TranslationProviderType $protocol): string
    {
        return $this->definitions()[$protocol->value]['icon'];
    }

    /**
     * 返回协议凭据字段的默认值。
     *
     * @return array<string, mixed>
     */
    public function defaultConfigurationForProtocol(TranslationProviderType $protocol): array
    {
        $configuration = [];

        foreach ($this->credentialFieldsForProtocol($protocol) as $field) {
            if (isset($field['default'])) {
                $configuration[$field['field']] = $field['default'];
            }
        }

        return $configuration;
    }
}
