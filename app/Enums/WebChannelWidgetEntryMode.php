<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 网站渠道小部件入口触发模式。
 * 区分使用 HelmDesk 默认气泡，或隐藏默认气泡后由客户站点自己的按钮触发聊天窗口。
 */
enum WebChannelWidgetEntryMode: string implements LabeledEnum
{
    case Bubble = 'bubble';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Bubble => __('channel.web_widget_entry_modes.bubble'),
            self::Custom => __('channel.web_widget_entry_modes.custom'),
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
            static fn (self $mode) => $mode->value,
            self::cases(),
        );
    }
}
