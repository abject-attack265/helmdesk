<?php

namespace App\Data\Channel\Web;

use App\Models\Channel;
use App\Services\Channel\WebChannelWidgetEntryIconResolver;
use Spatie\LaravelData\Data;

/**
 * 网站渠道小部件配置前端展示数据。
 */
class WebChannelWidgetData extends Data
{
    public function __construct(
        public ChannelWebWidgetEntryData $entry,
        public bool $unread_badge_enabled,
        public bool $inline_toast_enabled,
        public bool $mobile_fullscreen_enabled,
    ) {}

    /**
     * 从渠道模型组装小部件配置展示数据。
     *
     * @param  array<string, string|null>|null  $entryIconUrls
     */
    public static function fromModel(Channel $channel, ?array $entryIconUrls = null): self
    {
        $settings = $channel->settings instanceof ChannelWebSettingsData
            ? $channel->settings
            : ChannelWebSettingsData::defaults();

        $defaultWidget = ChannelWebWidgetSettingsData::defaults();
        $widget = $settings->widget;
        $entry = $widget->entry ?? $defaultWidget->entry ?? new ChannelWebWidgetEntryData;
        $resolver = app(WebChannelWidgetEntryIconResolver::class);

        return new self(
            entry: $entryIconUrls === null
                ? $resolver->resolve($entry)
                : $resolver->applyUrls($entry, $entryIconUrls),
            unread_badge_enabled: $widget->unread_badge_enabled,
            inline_toast_enabled: $widget->inline_toast_enabled,
            mobile_fullscreen_enabled: $widget->mobile_fullscreen_enabled,
        );
    }
}
