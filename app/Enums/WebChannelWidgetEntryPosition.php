<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 网站渠道小部件入口贴边位置。
 */
enum WebChannelWidgetEntryPosition: string implements LabeledEnum
{
    case Right = 'right';
    case Left = 'left';

    public function label(): string
    {
        return match ($this) {
            self::Right => __('channel.web_widget_entry_positions.right'),
            self::Left => __('channel.web_widget_entry_positions.left'),
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
            static fn (self $position) => $position->value,
            self::cases(),
        );
    }
}
