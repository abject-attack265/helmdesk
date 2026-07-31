<?php

namespace App\Data\Reception\PageProps;

use App\Data\Reception\ReceptionPlanData;
use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/**
 * 接待方案列表页 props。
 * 由 ShowReceptionPlanIndexPageAction 返回，下发给 resources/js/pages/reception/plans/List.vue。
 * 仅承载列表表格所需的精简方案数据与分页信息；创建、编辑、回收站分别由独立页面承接。
 */
class ShowReceptionPlanListPagePropsData extends Data
{
    public function __construct(
        /** @var ReceptionPlanData[] */
        public array $plan_list,
        public SimplePaginationData $plan_list_pagination,
    ) {}
}
