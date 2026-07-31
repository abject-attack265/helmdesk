<?php

namespace App\Data\CustomAttribute;

use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/**
 * 自定义属性回收站页面 props。
 */
class ShowAttributeDefinitionTrashPagePropsData extends Data
{
    public function __construct(
        /** @var ListAttributeDefinitionItemData[] */
        public array $trashed_definition_list,
        public SimplePaginationData $trashed_definition_list_pagination,
    ) {}
}
