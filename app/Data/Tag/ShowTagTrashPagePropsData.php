<?php

namespace App\Data\Tag;

use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/**
 * 标签回收站页面 props。
 * 由 ShowTagTrashAction 返回给 resources/js/pages/tags/Trash.vue，用于渲染已删除标签和分页状态。
 */
class ShowTagTrashPagePropsData extends Data
{
    public function __construct(
        /** @var ListTagItemData[] */
        public array $trashed_tag_list,
        public SimplePaginationData $trashed_tag_list_pagination,
    ) {}
}
