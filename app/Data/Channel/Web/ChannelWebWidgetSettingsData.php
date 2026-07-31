<?php

namespace App\Data\Channel\Web;

use Spatie\LaravelData\Data;

/**
 * 网站渠道小部件入口与跨端行为设置。
 * 主题色已收敛到共享的访客界面设置，这里只保留小部件特有的入口、提醒与移动端全屏配置。
 */
class ChannelWebWidgetSettingsData extends Data
{
    public function __construct(
        public ?ChannelWebWidgetEntryData $entry = null,
        public bool $unread_badge_enabled = false,
        public bool $inline_toast_enabled = false,
        public bool $mobile_fullscreen_enabled = true,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function defaults(array $overrides = []): self
    {
        return self::from(array_replace_recursive([
            'entry' => ChannelWebWidgetEntryData::defaults()->toArray(),
            'unread_badge_enabled' => false,
            'inline_toast_enabled' => false,
            'mobile_fullscreen_enabled' => true,
        ], $overrides));
    }
}
