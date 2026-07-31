<?php

namespace App\Data\Channel\WechatOfficialAccount;

use Spatie\LaravelData\Data;

/** 微信公众号渠道详情页面 Props。 */
class ShowWechatOfficialAccountChannelDetailPagePropsData extends Data
{
    /** 创建微信公众号渠道详情页面 Props。 */
    public function __construct(
        public WechatOfficialAccountData $wechat_channel,
        public WechatOfficialAccountFormOptionsData $form_options,
    ) {}
}
