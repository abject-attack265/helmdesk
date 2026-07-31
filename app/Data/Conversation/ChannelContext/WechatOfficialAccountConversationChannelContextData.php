<?php

namespace App\Data\Conversation\ChannelContext;

/** 微信公众号会话上下文。 */
class WechatOfficialAccountConversationChannelContextData extends ConversationChannelContextData
{
    public function __construct(
        public string $channel_type = 'wechat_oa',
        public ?string $openid = null,
        public ?string $nickname = null,
        public ?string $language = null,
        public ?string $captured_at = null,
    ) {}
}
