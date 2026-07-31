<?php

namespace App\Data\Tag;

use App\Data\EnumOptionData;
use Spatie\LaravelData\Data;

/**
 * 标签管理页 props。
 * 由 ShowTagListAction 返回给 resources/js/pages/tags/Index.vue：按 scope 分区，组内列出标签 chip。
 */
class ShowListTagPagePropsData extends Data
{
    public function __construct(
        /** @var ListTagGroupItemData[] */
        public array $tag_group_list,
        /** @var EnumOptionData[] */
        public array $scope_options,
    ) {}
}
