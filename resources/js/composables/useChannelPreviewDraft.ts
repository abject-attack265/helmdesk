/**
 * 文件说明：网站渠道详情页的「实时预览草稿」——贯穿所有 tab 的共享可编辑状态。
 *
 * 各配置 tab 直接读写这份 reactive 草稿（作为单一数据源驱动 hidden input 提交与右侧实时预览），
 * 右侧常驻预览面板（ChannelLivePreview）据此合成访客端数据，无需保存即可看到外观变化。
 * 仅承载「影响访客端外观」的字段；自定义传参等不影响外观的配置不进草稿。
 */
import type { WebChannelData } from '@/types/generated';
import { inject, provide, reactive, type InjectionKey } from 'vue';

/** 贯穿详情页、影响访客端外观的可编辑草稿字段。 */
export interface ChannelPreviewDraft {
  // 渠道基准（只读初值）
  code: string;
  channelName: string;
  iconUrl: string | null;
  serviceAvatarUrl: string | null;
  // 访客界面
  siteName: string;
  subtitle: string;
  headerEnabled: boolean;
  visitorIdentityMode: string;
  serviceDisplayName: string;
  greetingMessage: string;
  composerPlaceholder: string;
  themeColor: string;
  homeModeEnabled: boolean;
  homeWelcomeMessage: string;
  // 猜你想问
  suggestionsEnabled: boolean;
  suggestionItems: string[];
  // 小部件入口与设备适配（用于小部件形态的入口示意）
  entryMode: string;
  entryPosition: string;
  entryStyle: string;
  entryIconSize: string;
  entryBottomOffset: number;
  mobileFullscreenEnabled: boolean;
  // 自定义入口图标预览地址（style=custom 时生效）
  entryDefaultIconUrl: string | null;
  entrySelectedIconUrl: string | null;
}

const CHANNEL_PREVIEW_DRAFT_KEY: InjectionKey<ChannelPreviewDraft> = Symbol(
  'channel-preview-draft',
);

/** 按渠道展示数据初始化一份预览草稿。 */
export function createChannelPreviewDraft(
  channel: WebChannelData,
): ChannelPreviewDraft {
  const vi = channel.visitor_interface;

  return reactive<ChannelPreviewDraft>({
    code: channel.code,
    channelName: channel.name,
    iconUrl: vi.icon_url ?? null,
    serviceAvatarUrl: vi.service_avatar_url ?? null,
    siteName: vi.site_name ?? '',
    subtitle: vi.subtitle ?? '',
    headerEnabled: vi.header.enabled,
    visitorIdentityMode: vi.visitor_identity_mode,
    serviceDisplayName: vi.service_display_name ?? '',
    greetingMessage: vi.greeting_message ?? '',
    composerPlaceholder: vi.composer_placeholder ?? '',
    themeColor: vi.theme_color,
    homeModeEnabled: vi.home_mode_enabled,
    homeWelcomeMessage: vi.home_welcome_message ?? '',
    suggestionsEnabled: channel.suggestions.enabled,
    suggestionItems: [...channel.suggestions.items],
    entryMode: channel.widget.entry.mode,
    entryPosition: channel.widget.entry.position,
    entryStyle: channel.widget.entry.style,
    entryIconSize: channel.widget.entry.icon_size,
    entryBottomOffset: channel.widget.entry.bottom_offset,
    mobileFullscreenEnabled: channel.widget.mobile_fullscreen_enabled,
    entryDefaultIconUrl: channel.widget.entry.default_icon_url,
    entrySelectedIconUrl: channel.widget.entry.active_icon_url,
  });
}

/** 由详情页 Show.vue 向子树注入草稿。 */
export function provideChannelPreviewDraft(draft: ChannelPreviewDraft): void {
  provide(CHANNEL_PREVIEW_DRAFT_KEY, draft);
}

/** 在 tab / 预览组件内取用共享草稿；未注入时直接失败，避免静默脱节。 */
export function useChannelPreviewDraft(): ChannelPreviewDraft {
  const draft = inject(CHANNEL_PREVIEW_DRAFT_KEY);

  if (!draft) {
    throw new Error('ChannelPreviewDraft 未注入，请在渠道详情页内使用。');
  }

  return draft;
}
