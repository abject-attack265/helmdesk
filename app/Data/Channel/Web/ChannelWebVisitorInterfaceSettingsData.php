<?php

namespace App\Data\Channel\Web;

use App\Enums\WebChannelVisitorIdentityMode;
use App\Services\Channel\WebChannelThemePalette;
use Spatie\LaravelData\Data;

/**
 * 网站渠道访客界面设置。
 * 独立页与小部件共用标题栏、接待身份、欢迎语、统一主题色和首页模式等访客可见门面配置。
 */
class ChannelWebVisitorInterfaceSettingsData extends Data
{
    /**
     * 创建访客界面配置。
     *
     * theme_color 是独立页与小部件共用的统一主题色，驱动整套渐变背景与气泡视觉。
     * home_mode_enabled 开启时访客先看到欢迎屏（首页态），再进入聊天。
     */
    public function __construct(
        public ?string $site_name = null,
        public ?string $subtitle = null,
        public ?string $icon_id = null,
        public WebChannelVisitorIdentityMode $visitor_identity_mode = WebChannelVisitorIdentityMode::ActualReceptionist,
        public ?string $service_display_name = null,
        public ?string $service_avatar_id = null,
        public ?string $greeting_message = null,
        public ?ChannelWebHeaderData $header = null,
        public ?string $composer_placeholder = null,
        public string $theme_color = WebChannelThemePalette::DEFAULT,
        public bool $home_mode_enabled = false,
        public ?string $home_welcome_message = null,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function defaults(array $overrides = []): self
    {
        return self::from(array_replace_recursive([
            'header' => [
                'enabled' => false,
            ],
        ], $overrides));
    }
}
