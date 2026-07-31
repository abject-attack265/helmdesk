<?php

namespace App\Data\Translation;

use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/** 翻译供应商列表页数据。 */
class ShowTranslationProviderListPagePropsData extends Data
{
    /** 创建翻译供应商列表和分页数据。 */
    public function __construct(
        /** @var TranslationProviderData[] */
        public array $provider_list,
        public SimplePaginationData $provider_list_pagination,
    ) {}
}
