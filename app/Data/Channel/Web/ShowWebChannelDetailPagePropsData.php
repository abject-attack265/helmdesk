<?php

namespace App\Data\Channel\Web;

use Spatie\LaravelData\Data;

/**
 * 网站渠道详情页面 props。
 * 由对应 Show*Action 返回给 resources/js/pages/channel/web/List.vue、Show.vue 及 tabs/*，用于渲染首屏数据、筛选项和页面状态。
 */
class ShowWebChannelDetailPagePropsData extends Data
{
    public function __construct(
        public WebChannelData $web_channel,
        public WebChannelFormOptionsData $form_options,
    ) {}
}
