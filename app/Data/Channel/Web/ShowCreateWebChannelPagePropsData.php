<?php

namespace App\Data\Channel\Web;

use App\Data\EnumOptionData;
use App\Data\Reception\Plan\ReceptionPlanOptionData;
use Spatie\LaravelData\Data;

/**
 * 创建网站渠道页面 props。
 */
class ShowCreateWebChannelPagePropsData extends Data
{
    public function __construct(
        /** @var ReceptionPlanOptionData[] */
        public array $reception_plan_options,
        /** @var EnumOptionData[] */
        public array $reception_language_options,
    ) {}
}
