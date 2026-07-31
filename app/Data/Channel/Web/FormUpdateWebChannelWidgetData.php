<?php

namespace App\Data\Channel\Web;

use App\Enums\WebChannelWidgetEntryMode;
use App\Enums\WebChannelWidgetEntryPosition;
use App\Enums\WebChannelWidgetEntryStyle;
use App\Enums\WebChannelWidgetIconSize;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 更新网站渠道小部件入口与跨端表单数据。
 * 来自 resources/js/pages/channel/web/tabs/EntryDeviceTab.vue 的入口/设备表单提交，
 * 后端用它校验并保存入口模式、贴边位置、图标、提醒与移动端全屏配置；嵌入域名白名单由接入方式表单单独维护。
 */
class FormUpdateWebChannelWidgetData extends Data
{
    public function __construct(
        public WebChannelWidgetEntryMode $entry_mode = WebChannelWidgetEntryMode::Bubble,
        public WebChannelWidgetEntryPosition $entry_position = WebChannelWidgetEntryPosition::Right,
        public WebChannelWidgetEntryStyle $entry_style = WebChannelWidgetEntryStyle::System,
        public WebChannelWidgetIconSize $entry_icon_size = WebChannelWidgetIconSize::Large,
        public int $entry_bottom_offset = 30,
        public ?string $entry_default_icon_id = null,
        public ?string $entry_active_icon_id = null,
        public bool $unread_badge_enabled = false,
        public bool $inline_toast_enabled = false,
        public bool $mobile_fullscreen_enabled = true,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'entry_mode' => ['required', 'string', Rule::in(WebChannelWidgetEntryMode::values())],
            'entry_position' => ['required', 'string', Rule::in(WebChannelWidgetEntryPosition::values())],
            'entry_style' => ['required', 'string', Rule::in(WebChannelWidgetEntryStyle::values())],
            'entry_icon_size' => ['required', 'string', Rule::in(WebChannelWidgetIconSize::values())],
            'entry_bottom_offset' => ['required', 'integer', 'min:'.ChannelWebWidgetEntryData::MinBottomOffset, 'max:'.ChannelWebWidgetEntryData::MaxBottomOffset],
            // 使用 HelmDesk 默认气泡且选择自定义图标时，默认/选中图标需成对出现。
            'entry_default_icon_id' => ['exclude_if:entry_mode,custom', 'nullable', 'string', 'required_with:entry_active_icon_id'],
            'entry_active_icon_id' => ['exclude_if:entry_mode,custom', 'nullable', 'string', 'required_with:entry_default_icon_id'],
            'unread_badge_enabled' => ['required', 'boolean'],
            'inline_toast_enabled' => ['required', 'boolean'],
            'mobile_fullscreen_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * 返回自定义校验文案：自定义入口图标必须成对上传。
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'entry_default_icon_id.required_with' => __('channel.messages.entry_icon_pair_required'),
            'entry_active_icon_id.required_with' => __('channel.messages.entry_icon_pair_required'),
        ];
    }
}
