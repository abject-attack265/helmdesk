<?php

namespace App\Data\Channel\Web;

use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/**
 * 网站渠道页面 props。
 * 由对应 Show*Action 返回给 resources/js/pages/channel/web/List.vue、Show.vue 及 tabs/*，用于渲染首屏数据、筛选项和页面状态。
 */
class ShowWebChannelListPagePropsData extends Data
{
    public function __construct(
        /** @var WebChannelData[] */
        public array $channel_list,
        public SimplePaginationData $channel_list_pagination,
    ) {}
}
