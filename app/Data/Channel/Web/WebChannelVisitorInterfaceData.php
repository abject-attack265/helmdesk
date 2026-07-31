<?php

namespace App\Data\Channel\Web;

use App\Enums\WebChannelVisitorIdentityMode;
use App\Models\Attachment;
use App\Models\Channel;
use Spatie\LaravelData\Data;

/**
 * 网站渠道访客界面前端展示数据。
 * 由详情页的“访客界面”标签页消费，统一编辑独立页和小部件共享的访客可见内容。
 */
class WebChannelVisitorInterfaceData extends Data
{
    public function __construct(
        public ?string $site_name,
        public string $effective_site_name,
        public ?string $subtitle,
        public ?string $icon_id,
        public ?string $icon_url,
        public WebChannelVisitorIdentityMode $visitor_identity_mode,
        public string $visitor_identity_mode_label,
        public ?string $service_display_name,
        public ?string $service_avatar_id,
        public ?string $service_avatar_url,
        public ?string $greeting_message,
        public ChannelWebHeaderData $header,
        public ?string $composer_placeholder,
        public string $theme_color,
        public bool $home_mode_enabled,
        public ?string $home_welcome_message,
    ) {}

    /**
     * 从渠道模型组装访客界面展示数据。
     */
    public static function fromModel(Channel $channel): self
    {
        $settings = $channel->settings instanceof ChannelWebSettingsData
            ? $channel->settings
            : ChannelWebSettingsData::defaults();
        $visitorInterface = $settings->visitor_interface;
        $iconId = $visitorInterface->icon_id;
        $serviceAvatarId = $visitorInterface->service_avatar_id;
        $visitorIdentityMode = $visitorInterface->visitor_identity_mode;

        return new self(
            site_name: filled($visitorInterface->site_name) ? $visitorInterface->site_name : null,
            effective_site_name: filled($visitorInterface->site_name) ? $visitorInterface->site_name : $channel->name,
            subtitle: filled($visitorInterface->subtitle) ? $visitorInterface->subtitle : null,
            icon_id: filled($iconId) ? (string) $iconId : null,
            icon_url: Attachment::findUrl($iconId),
            visitor_identity_mode: $visitorIdentityMode,
            visitor_identity_mode_label: $visitorIdentityMode->label(),
            service_display_name: filled($visitorInterface->service_display_name) ? $visitorInterface->service_display_name : null,
            service_avatar_id: filled($serviceAvatarId) ? (string) $serviceAvatarId : null,
            service_avatar_url: Attachment::findUrl($serviceAvatarId),
            greeting_message: filled($visitorInterface->greeting_message) ? $visitorInterface->greeting_message : null,
            header: $visitorInterface->header ?? new ChannelWebHeaderData,
            composer_placeholder: filled($visitorInterface->composer_placeholder) ? $visitorInterface->composer_placeholder : null,
            theme_color: $visitorInterface->theme_color,
            home_mode_enabled: $visitorInterface->home_mode_enabled,
            home_welcome_message: filled($visitorInterface->home_welcome_message) ? $visitorInterface->home_welcome_message : null,
        );
    }
}
