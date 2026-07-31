<?php

namespace App\Data\Reception\PageProps;

use App\Data\Reception\ReceptionPlanData;
use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/**
 * 接待方案回收站页 props。
 * 由 ListReceptionPlanTrashAction 返回，下发给 resources/js/pages/reception/plans/Trash.vue。
 * 承载分页的已删除方案列表，供查看与恢复。
 */
class ListReceptionPlanTrashPagePropsData extends Data
{
    public function __construct(
        /** @var ReceptionPlanData[] */
        public array $trashed_plan_list,
        public SimplePaginationData $trashed_plan_list_pagination,
    ) {}
}
