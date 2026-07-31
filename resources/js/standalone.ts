/**
 * 文件说明：独立访客端入口，用于加载不依赖后台壳层的页面。
 */
import '../css/app.css';

import type { PublicStandaloneChannelData } from '@/types/generated';
import { createApp } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { initializeStandaloneLocale } from './standalone/i18n';
import StandaloneRoot from './standalone/StandaloneRoot.vue';

declare global {
  interface Window {
    __HELMDESK_STANDALONE__?: {
      channel: PublicStandaloneChannelData;
      // 可选签名身份：容器可在壳层注入，浏览器场景由前端从 URL 读取。
      user_token?: string | null;
    };
  }
}

// 独立页不使用后台的 `@/composables/useI18n` 与 `@/composables/useTimezone`，
// 以避免把后台语言包和 `@vvo/tzdb` 打进独立页 bundle。
initializeTheme();
initializeStandaloneLocale();

const mountElement = document.getElementById('app');
const bootstrap = window.__HELMDESK_STANDALONE__;

if (mountElement && bootstrap) {
  createApp(StandaloneRoot, {
    channel: bootstrap.channel,
    userToken: bootstrap.user_token ?? null,
  }).mount(mountElement);
}
