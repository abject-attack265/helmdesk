<?php

namespace App\Data\Translation;

use Spatie\LaravelData\Data;

/** 翻译供应商编辑页数据。 */
class ShowEditTranslationProviderPagePropsData extends Data
{
    /** 创建翻译供应商编辑页数据。 */
    public function __construct(
        public TranslationProviderData $provider,
    ) {}
}
