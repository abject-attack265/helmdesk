<?php

namespace App\Data\AiProvider;

use App\Enums\AiProviderProtocol;
use App\Models\AiProvider;
use App\Services\AiProvider\AiProviderCatalog;
use Spatie\LaravelData\Data;

/**
 * AI 供应商展示数据（纯凭据，不含模型）。
 *
 * 由应用设置 AI 供应商管理 Action 组装，传给 resources/js/pages/appSettings/aiProvider/*：渲染列表与编辑表单。
 * credential_values 下发全部凭据字段明文供编辑表单回显（页面仅系统所有者可达）；secret 字段由前端以密码框呈现。
 * protocol/brand 以 Enum/字符串下发；模型在「AI 模型管理」页单独维护。
 */
class AiProviderData extends Data
{
    public function __construct(
        public string $id,
        public string $brand,
        public string $brand_label,
        public bool $is_custom,
        public string $slug,
        public string $name,
        public AiProviderProtocol $protocol,
        public ?string $base_url,
        /** @var array<int, array<string, mixed>> */
        public array $credential_fields,
        /** @var array<string, string|null> */
        public array $credential_values,
    ) {}

    /**
     * 把 Eloquent 模型转成前端可消费的 DTO。
     */
    public static function fromModel(AiProvider $provider): self
    {
        $credentials = $provider->credentials;
        $credentialValues = [];

        foreach ($provider->credential_fields as $field) {
            $fieldName = $field['field'] ?? null;
            if (! is_string($fieldName)) {
                continue;
            }

            $value = is_array($credentials) ? ($credentials[$fieldName] ?? null) : null;
            $credentialValues[$fieldName] = is_scalar($value) ? (string) $value : null;
        }

        $protocol = $provider->protocol instanceof AiProviderProtocol
            ? $provider->protocol
            : AiProviderProtocol::from((string) $provider->protocol);

        $catalog = app(AiProviderCatalog::class);
        $baseUrl = $credentialValues['base_uri'] ?? null;

        return new self(
            id: $provider->id,
            brand: $provider->brand,
            brand_label: $catalog->labelForBrand($provider->brand),
            is_custom: $catalog->isCustomBrand($provider->brand),
            slug: $provider->slug,
            name: $provider->name,
            protocol: $protocol,
            base_url: filled($baseUrl) ? (string) $baseUrl : null,
            credential_fields: $provider->credential_fields,
            credential_values: $credentialValues,
        );
    }
}
