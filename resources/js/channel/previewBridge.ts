/**
 * 文件说明：网站渠道详情页右侧实时预览的 postMessage 协议。
 * 后台父页面（ChannelLivePreview）与预览 iframe（ChannelPreviewRoot）共用这份契约，
 * 渠道外观草稿据此在同源 iframe 内实时渲染，无需保存。
 */
import type { PublicStandaloneChannelData } from '@/types/generated';

/** iframe 挂载完成、可以接收渲染指令时通知父页面。 */
export const CHANNEL_PREVIEW_READY = 'helmdesk:channel-preview:ready';
/** 父页面向 iframe 下发一帧待渲染的访客端数据。 */
export const CHANNEL_PREVIEW_RENDER = 'helmdesk:channel-preview:render';

/** 父页面 → iframe 的渲染指令载荷。 */
export interface ChannelPreviewRenderPayload {
  type: typeof CHANNEL_PREVIEW_RENDER;
  /** 由草稿合成的访客端公开数据。 */
  channel: PublicStandaloneChannelData;
  /** 演示模式：可交互且本地回显，不连后端（小部件形态使用）。 */
  demo: boolean;
  /** 视图重置键：home 模式 / 形态切换时变化，用于强制重挂画布。 */
  resetKey: string;
}

/** iframe → 父页面的就绪通知载荷。 */
export interface ChannelPreviewReadyPayload {
  type: typeof CHANNEL_PREVIEW_READY;
}
