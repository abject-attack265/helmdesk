<?php

namespace App\Actions\Channel\Web;

use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Channel\Web\FormUpdateWebChannelVisitorInterfaceData;
use App\Enums\AttachmentPurpose;
use App\Enums\WebChannelVisitorIdentityMode;
use App\Models\Channel;
use App\Models\User;
use App\Services\Channel\WebChannelResolutionService;
use App\Services\Channel\WebChannelThemePalette;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新网站渠道访客界面配置。
 */
class UpdateWebChannelVisitorInterfaceAction
{
    use AsAction;

    /**
     * 注入渠道解析服务。
     */
    public function __construct(
        private readonly WebChannelResolutionService $resolution,
    ) {}

    /**
     * 保存独立页和小部件共用的标题栏、接待身份和欢迎语配置。
     */
    public function handle(User $actor, Channel $channel, FormUpdateWebChannelVisitorInterfaceData $data): void
    {
        $siteName = $data->header_enabled ? $this->blankToNull($data->site_name) : null;
        $subtitle = $data->header_enabled ? $this->blankToNull($data->subtitle) : null;
        $iconId = $data->header_enabled ? $this->blankToNull($data->icon_id) : null;
        $serviceDisplayName = $this->serviceValue($data, $data->service_display_name);
        $serviceAvatarId = $this->serviceValue($data, $data->service_avatar_id);
        $homeWelcomeMessage = $data->home_mode_enabled ? $this->blankToNull($data->home_welcome_message) : null;

        UpdateWebChannelBasicAction::assertAttachmentAssignable($actor, $channel, AttachmentPurpose::ChannelIcon, $iconId);
        UpdateWebChannelBasicAction::assertAttachmentAssignable($actor, $channel, AttachmentPurpose::Avatar, $serviceAvatarId);

        /** @var ChannelWebSettingsData $currentSettings */
        $currentSettings = $channel->settings;

        $settings = $currentSettings->mergeWith([
            'visitor_interface' => [
                'site_name' => $siteName,
                'subtitle' => $subtitle,
                'icon_id' => $iconId,
                'visitor_identity_mode' => $data->visitor_identity_mode->value,
                'service_display_name' => $serviceDisplayName,
                'service_avatar_id' => $serviceAvatarId,
                'greeting_message' => $this->blankToNull($data->greeting_message),
                'header' => [
                    'enabled' => $data->header_enabled,
                ],
                'composer_placeholder' => $this->blankToNull($data->composer_placeholder),
                'theme_color' => WebChannelThemePalette::normalize($data->theme_color),
                'home_mode_enabled' => $data->home_mode_enabled,
                'home_welcome_message' => $homeWelcomeMessage,
            ],
            'suggestions' => [
                'enabled' => $data->suggestions_enabled,
                'items' => $data->normalizedSuggestionItems(),
            ],
        ]);

        DB::transaction(function () use ($actor, $channel, $iconId, $serviceAvatarId, $settings): void {
            $channel->update([
                'settings' => $settings,
            ]);

            UpdateWebChannelBasicAction::bindChannelAttachment($actor, $channel, $iconId);
            UpdateWebChannelBasicAction::bindChannelAttachment($actor, $channel, $serviceAvatarId);
        });
    }

    /**
     * 接收访客界面表单并返回详情页。
     */
    public function asController(Request $request, string $channel): RedirectResponse
    {

        $channelModel = $this->resolution->find($channel);

        $this->handle($request->user(), $channelModel, FormUpdateWebChannelVisitorInterfaceData::from($request));

        return redirect()->back(302, [], route('app.manage.channels.web.show', [
            'channel' => $channelModel->id,
        ]));
    }

    /**
     * 将空白字符串整理为 null。
     */
    private function blankToNull(?string $value): ?string
    {
        return filled($value) ? $value : null;
    }

    /**
     * 仅在统一客服身份模式下保留客服身份字段。
     */
    private function serviceValue(FormUpdateWebChannelVisitorInterfaceData $data, ?string $value): ?string
    {
        if ($data->visitor_identity_mode !== WebChannelVisitorIdentityMode::UnifiedService) {
            return null;
        }

        return $this->blankToNull($value);
    }
}
