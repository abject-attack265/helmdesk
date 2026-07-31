<?php

namespace App\Data\Channel\WechatOfficialAccount;

use App\Data\EnumOptionData;
use App\Data\Reception\Plan\ReceptionPlanOptionData;
use Spatie\LaravelData\Data;

/** 微信公众号渠道表单选项。 */
class WechatOfficialAccountFormOptionsData extends Data
{
    /** 创建微信公众号渠道表单选项。 */
    public function __construct(
        /** @var ReceptionPlanOptionData[] */
        public array $reception_plan_options,
        /** @var EnumOptionData[] */
        public array $reception_language_options,
    ) {}
}
