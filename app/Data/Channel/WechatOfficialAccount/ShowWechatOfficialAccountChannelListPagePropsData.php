<?php

namespace App\Data\Channel\WechatOfficialAccount;

use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/** 微信公众号渠道列表页面 Props。 */
class ShowWechatOfficialAccountChannelListPagePropsData extends Data
{
    /** 创建微信公众号渠道列表页面 Props。 */
    public function __construct(
        /** @var WechatOfficialAccountData[] */
        public array $channel_list,
        public SimplePaginationData $channel_list_pagination,
    ) {}
}
