<?php

namespace App\Data\Channel\WechatOfficialAccount;

use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/** 微信公众号渠道回收站页面 Props。 */
class ShowWechatOfficialAccountChannelTrashPagePropsData extends Data
{
    /** 创建微信公众号渠道回收站页面 Props。 */
    public function __construct(
        /** @var WechatOfficialAccountData[] */
        public array $trashed_channel_list,
        public SimplePaginationData $trashed_channel_list_pagination,
    ) {}
}
