<?php

namespace App\Data\Channel\Web;

use App\Enums\WebChannelWidgetEntryMode;
use App\Enums\WebChannelWidgetEntryPosition;
use App\Enums\WebChannelWidgetEntryStyle;
use App\Enums\WebChannelWidgetIconSize;
use Spatie\LaravelData\Data;

/**
 * 网站渠道小部件收起入口配置。
 * 描述入口触发模式、聊天窗贴边位置、系统默认样式参数，以及 style=custom 时的默认/选中自定义图标。
 * *_icon_id 为入库的附件 ID；*_icon_url 是展示态解析出的可访问地址，不入库。
 */
class ChannelWebWidgetEntryData extends Data
{
    public const int MinBottomOffset = 0;

    public const int MaxBottomOffset = 120;

    public function __construct(
        public WebChannelWidgetEntryMode $mode = WebChannelWidgetEntryMode::Bubble,
        public WebChannelWidgetEntryPosition $position = WebChannelWidgetEntryPosition::Right,
        public WebChannelWidgetEntryStyle $style = WebChannelWidgetEntryStyle::System,
        public WebChannelWidgetIconSize $icon_size = WebChannelWidgetIconSize::Large,
        public int $bottom_offset = 30,
        public ?string $default_icon_id = null,
        public ?string $active_icon_id = null,
        public ?string $default_icon_url = null,
        public ?string $active_icon_url = null,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function defaults(array $overrides = []): self
    {
        return self::from(array_replace_recursive([
            'mode' => WebChannelWidgetEntryMode::Bubble->value,
            'position' => WebChannelWidgetEntryPosition::Right->value,
            'style' => WebChannelWidgetEntryStyle::System->value,
            'icon_size' => WebChannelWidgetIconSize::Large->value,
            'bottom_offset' => 30,
            'default_icon_id' => null,
            'active_icon_id' => null,
        ], $overrides));
    }

    /**
     * 返回带自定义图标展示 URL 的副本；URL 字段不入库。
     */
    public function withIconUrls(?string $defaultIconUrl, ?string $activeIconUrl): self
    {
        return new self(
            mode: $this->mode,
            position: $this->position,
            style: $this->style,
            icon_size: $this->icon_size,
            bottom_offset: $this->bottom_offset,
            default_icon_id: $this->default_icon_id,
            active_icon_id: $this->active_icon_id,
            default_icon_url: $defaultIconUrl,
            active_icon_url: $activeIconUrl,
        );
    }
}
