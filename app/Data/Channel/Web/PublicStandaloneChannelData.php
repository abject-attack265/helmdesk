<?php

namespace App\Data\Channel\Web;

use App\Enums\WebChannelVisitorIdentityMode;
use App\Models\Attachment;
use App\Models\Channel;
use App\Models\ReceptionPlanVersion;
use App\Services\Channel\WebChannelWidgetEntryIconResolver;
use App\Services\Reception\ChannelActivePlanVersionResolver;
use Spatie\LaravelData\Data;

/**
 * 访客独立页的公开渠道数据。
 * 由公开网站渠道接口返回，resources/js/standalone/StandaloneRoot.vue 和 widget/WidgetRoot.vue 用它渲染品牌、问候语和接待入口。
 */
class PublicStandaloneChannelData extends Data
{
    /**
     * 创建公开独立页渠道数据。
     *
     * entry / unread_badge_enabled / inline_toast_enabled / mobile_fullscreen_enabled 仅在 widget bootstrap 时填充：
     * 独立页内访客始终能看到自己的未读，且天然全屏，因此独立页这些字段维持 null。
     *
     * paused 为 true 时渠道已被管理员软删除，访客端应渲染"暂时不可用"占位，
     * 仍允许已有会话继续消息往返，但拒绝新建会话。
     */
    public function __construct(
        public string $code,
        public string $site_name,
        public ?string $subtitle,
        public string $assistant_name,
        public ?string $assistant_avatar_url,
        public ?string $icon_url,
        public ?string $greeting_message,
        public string $theme_color,
        public bool $home_mode_enabled,
        public ?string $home_welcome_message,
        public ChannelWebHeaderData $header,
        public ChannelWebComposerData $composer,
        public ChannelWebSuggestionsData $suggestions,
        public ?ChannelWebWidgetEntryData $entry = null,
        public ?bool $unread_badge_enabled = null,
        public ?bool $inline_toast_enabled = null,
        public ?bool $mobile_fullscreen_enabled = null,
        public bool $paused = false,
    ) {}

    /**
     * 从渠道模型组装公开独立页数据。
     *
     * $paused 由调用方决定：bootstrap action 检测到渠道已 trashed 时传 true，
     * 此时仍按已保存配置渲染品牌/入口，避免客户站点上小部件入口突兀消失。
     */
    public static function fromModel(Channel $channel, bool $useWidgetSettings = false, bool $paused = false): self
    {
        $settings = $channel->settings instanceof ChannelWebSettingsData
            ? $channel->settings
            : ChannelWebSettingsData::defaults();
        $visitorInterface = $settings->visitor_interface;

        $planVersion = app(ChannelActivePlanVersionResolver::class)->currentVersionForChannel($channel);
        $persona = self::personaFromPlanVersion($planVersion);
        $visitorIdentityMode = $visitorInterface->visitor_identity_mode;

        $assistantName = match ($visitorIdentityMode) {
            WebChannelVisitorIdentityMode::UnifiedService => filled($visitorInterface->service_display_name)
                ? (string) $visitorInterface->service_display_name
                : $channel->name,
            WebChannelVisitorIdentityMode::ActualReceptionist => $persona['display_name']
                ?? (string) __('channel.defaults.assistant_name'),
        };

        $assistantAvatarUrl = $visitorIdentityMode === WebChannelVisitorIdentityMode::UnifiedService
            ? Attachment::findUrl($visitorInterface->service_avatar_id)
            : null;

        return new self(
            code: $channel->code,
            site_name: filled($visitorInterface->site_name) ? $visitorInterface->site_name : $channel->name,
            subtitle: filled($visitorInterface->subtitle) ? $visitorInterface->subtitle : null,
            assistant_name: $assistantName,
            assistant_avatar_url: $assistantAvatarUrl,
            icon_url: Attachment::findUrl($visitorInterface->icon_id),
            greeting_message: filled($visitorInterface->greeting_message) ? $visitorInterface->greeting_message : null,
            theme_color: $visitorInterface->theme_color,
            home_mode_enabled: $visitorInterface->home_mode_enabled,
            home_welcome_message: filled($visitorInterface->home_welcome_message) ? $visitorInterface->home_welcome_message : null,
            header: $visitorInterface->header ?? new ChannelWebHeaderData,
            composer: ChannelWebComposerData::fromVisitorInterface($visitorInterface),
            suggestions: $settings->suggestions,
            entry: $useWidgetSettings
                ? app(WebChannelWidgetEntryIconResolver::class)->resolve($settings->widget->entry ?? ChannelWebWidgetEntryData::defaults())
                : null,
            unread_badge_enabled: $useWidgetSettings ? $settings->widget->unread_badge_enabled : null,
            inline_toast_enabled: $useWidgetSettings ? $settings->widget->inline_toast_enabled : null,
            mobile_fullscreen_enabled: $useWidgetSettings ? $settings->widget->mobile_fullscreen_enabled : null,
            paused: $paused,
        );
    }

    /**
     * 从接待方案版本快照取 persona 展示信息。
     *
     * @return array{display_name: ?string}
     */
    private static function personaFromPlanVersion(?ReceptionPlanVersion $planVersion): array
    {
        if ($planVersion === null) {
            return ['display_name' => null];
        }

        $displayName = $planVersion->personaConfig()->display_name;

        return ['display_name' => filled($displayName) ? $displayName : null];
    }
}
