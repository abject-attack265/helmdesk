<?php

namespace App\Data\Translation;

use App\Enums\TranslationProviderType;
use App\Models\TranslationProvider;
use App\Services\Translation\TranslationProviderCatalog;
use Spatie\LaravelData\Data;

/** 翻译供应商列表与编辑表单使用的展示数据。 */
class TranslationProviderData extends Data
{
    /** 创建翻译供应商展示数据。 */
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public TranslationProviderType $protocol,
        public string $protocol_label,
        public string $icon,
        /** @var array<int, array<string, mixed>> */
        public array $credential_fields,
        /** @var array<string, string|null> */
        public array $credential_values,
        public bool $is_active,
    ) {}

    /**
     * 从翻译供应商模型创建展示数据。
     */
    public static function fromModel(TranslationProvider $provider): self
    {
        $credentials = $provider->credentials ?? [];
        $credentialValues = [];

        foreach ($provider->credential_fields as $field) {
            $fieldName = $field['field'];
            $credentialValues[$fieldName] = isset($credentials[$fieldName])
                ? (string) $credentials[$fieldName]
                : null;
        }

        $protocol = $provider->protocol;

        return new self(
            id: $provider->id,
            slug: $provider->slug,
            name: $provider->name,
            protocol: $protocol,
            protocol_label: $protocol->label(),
            icon: $provider->icon
                ?? app(TranslationProviderCatalog::class)->iconForProtocol($protocol),
            credential_fields: $provider->credential_fields,
            credential_values: $credentialValues,
            is_active: $provider->is_active,
        );
    }
}
