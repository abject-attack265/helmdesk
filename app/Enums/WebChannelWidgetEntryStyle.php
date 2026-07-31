<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 网站渠道小部件入口视觉来源。
 */
enum WebChannelWidgetEntryStyle: string implements LabeledEnum
{
    case System = 'system';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::System => __('channel.web_widget_entry_styles.system'),
            self::Custom => __('channel.web_widget_entry_styles.custom'),
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
            static fn (self $style) => $style->value,
            self::cases(),
        );
    }
}
