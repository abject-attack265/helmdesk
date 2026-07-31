<?php

namespace App\Data\Translation;

use App\Data\CredentialFieldData;
use App\Data\EnumOptionData;
use Spatie\LaravelData\Data;

/** 翻译供应商创建页数据。 */
class ShowCreateTranslationProviderPagePropsData extends Data
{
    /**
     * 创建协议选项、图标与凭据字段页面数据。
     */
    public function __construct(
        /** @var EnumOptionData[] */
        public array $protocol_options,
        /** @var array<string, CredentialFieldData[]> */
        public array $protocol_credential_fields,
        /** @var array<string, string> */
        public array $protocol_icons,
    ) {}
}
