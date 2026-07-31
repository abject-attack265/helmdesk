<?php

namespace App\Data\Conversation\ChannelContext;

/**
 * Web 渠道会话上下文：访客在网站上的行为与环境快照。
 *
 * 入站时透传原始信号（url/referrer/user_agent/ip），
 * 派生字段（browser/os/device_type、geo_*）由后端 Service 在落库前补齐。
 * 展示在坐席收件箱右侧的渠道上下文块。
 */
class WebConversationChannelContextData extends ConversationChannelContextData
{
    public function __construct(
        public string $channel_type = 'web',
        public ?string $current_url = null,
        public ?string $entry_url = null,
        public ?string $landing_url = null,
        public ?string $referrer = null,
        public ?string $user_agent = null,
        public ?string $ip_address = null,
        public ?string $browser_language = null,
        public ?string $browser = null,
        public ?string $browser_version = null,
        public ?string $platform = null,
        public ?string $device_type = null,
        public ?string $geo_country = null,
        public ?string $geo_region = null,
        public ?string $geo_city = null,
        public ?string $utm_source = null,
        public ?string $utm_medium = null,
        public ?string $utm_campaign = null,
        public ?string $ref = null,
        public ?string $captured_at = null,
    ) {}
}
