<?php

namespace App\Data\CustomAttribute;

use App\Data\EnumOptionData;
use Spatie\LaravelData\Data;

/**
 * 属性定义页面 props。
 */
class ShowListAttributeDefinitionPagePropsData extends Data
{
    public function __construct(
        /** @var ListAttributeDefinitionItemData[] */
        public array $definition_list,
        /** @var EnumOptionData[] */
        public array $type_options,
    ) {}
}
