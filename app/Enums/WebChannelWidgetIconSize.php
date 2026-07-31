<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 网站渠道小部件系统默认入口图标尺寸。
 */
enum WebChannelWidgetIconSize: string implements LabeledEnum
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => __('channel.web_widget_icon_sizes.small'),
            self::Medium => __('channel.web_widget_icon_sizes.medium'),
            self::Large => __('channel.web_widget_icon_sizes.large'),
        };
    }

    /**
     * 返回该尺寸对应的像素边长。
     */
    public function pixels(): int
    {
        return match ($this) {
            self::Small => 36,
            self::Medium => 48,
            self::Large => 52,
        };
    }

    /**
     * 返回可用于表单校验的枚举值列表。
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $size) => $size->value,
            self::cases(),
        );
    }
}
