<?php

namespace App\Data\Reception\PageProps;

use App\Data\EnumOptionData;
use Spatie\LaravelData\Data;

/**
 * 创建接待方案页 props。
 * 由 ShowCreateReceptionPlanPageAction 返回，下发给 resources/js/pages/reception/plans/Create.vue。
 * 创建仅收集基础信息 + 人设，其余配置在创建后于详情页完善。
 */
class CreateReceptionPlanPagePropsData extends Data
{
    public function __construct(
        /** @var EnumOptionData[] */
        public array $persona_tone_options,
    ) {}
}
