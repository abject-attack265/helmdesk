<?php

namespace App\Actions\Channel\Web;

use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Channel\Web\FormUpdateWebChannelWidgetData;
use App\Enums\AttachmentPurpose;
use App\Enums\WebChannelWidgetEntryMode;
use App\Enums\WebChannelWidgetEntryStyle;
use App\Models\Channel;
use App\Models\User;
use App\Services\Channel\WebChannelResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新网站渠道小部件入口配置。
 */
class UpdateWebChannelWidgetAction
{
    use AsAction;

    /**
     * 注入渠道解析服务。
     */
    public function __construct(
        private readonly WebChannelResolutionService $resolution,
    ) {}

    /**
     * 保存网站渠道小部件入口、提醒与移动端全屏配置。
     */
    public function handle(User $actor, Channel $channel, FormUpdateWebChannelWidgetData $data): void
    {
        $usesDefaultBubble = $data->entry_mode === WebChannelWidgetEntryMode::Bubble;
        $usesCustomIcons = $usesDefaultBubble && $data->entry_style === WebChannelWidgetEntryStyle::Custom;
        $defaultIconId = $usesCustomIcons && filled($data->entry_default_icon_id) ? $data->entry_default_icon_id : null;
        $activeIconId = $usesCustomIcons && filled($data->entry_active_icon_id) ? $data->entry_active_icon_id : null;

        // 自定义入口图标复用渠道图标用途，需校验归属、状态与 purpose 后再绑定到渠道。
        UpdateWebChannelBasicAction::assertAttachmentAssignable($actor, $channel, AttachmentPurpose::ChannelIcon, $defaultIconId);
        UpdateWebChannelBasicAction::assertAttachmentAssignable($actor, $channel, AttachmentPurpose::ChannelIcon, $activeIconId);

        /** @var ChannelWebSettingsData $currentSettings */
        $currentSettings = $channel->settings;

        $settingsOverrides = [
            'widget' => [
                'entry' => [
                    'mode' => $data->entry_mode->value,
                    'position' => $data->entry_position->value,
                    'style' => $data->entry_style->value,
                    'icon_size' => $data->entry_icon_size->value,
                    'bottom_offset' => $data->entry_bottom_offset,
                    'default_icon_id' => $defaultIconId,
                    'active_icon_id' => $activeIconId,
                ],
                'unread_badge_enabled' => $usesDefaultBubble && $data->unread_badge_enabled,
                'inline_toast_enabled' => $usesDefaultBubble && $data->inline_toast_enabled,
                'mobile_fullscreen_enabled' => $data->mobile_fullscreen_enabled,
            ],
        ];

        $settings = $currentSettings->mergeWith($settingsOverrides);

        DB::transaction(function () use ($actor, $channel, $settings, $defaultIconId, $activeIconId): void {
            $channel->update([
                'settings' => $settings,
            ]);

            UpdateWebChannelBasicAction::bindChannelAttachment($actor, $channel, $defaultIconId);
            UpdateWebChannelBasicAction::bindChannelAttachment($actor, $channel, $activeIconId);
        });
    }

    /**
     * 接收小部件配置表单并返回详情页。
     */
    public function asController(Request $request, string $channel): RedirectResponse
    {

        $channelModel = $this->resolution->find($channel);

        $this->handle($request->user(), $channelModel, FormUpdateWebChannelWidgetData::from($request));

        return redirect()->back(302, [], route('app.manage.channels.web.show', [
            'channel' => $channelModel->id,
        ]));
    }
}
