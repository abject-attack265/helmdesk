/**
 * 文件说明：网站渠道小部件 iframe 入口，用于加载可嵌入第三方网站的访客聊天界面。
 */
import '../css/app.css';

import type { PublicStandaloneChannelData } from '@/types/generated';
import { createApp } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { initializeStandaloneLocale } from './standalone/i18n';
import WidgetRoot from './widget/WidgetRoot.vue';

declare global {
  interface Window {
    __HELMDESK_WIDGET__?: {
      channel: PublicStandaloneChannelData;
    };
  }
}

initializeTheme();
initializeStandaloneLocale();

const mountElement = document.getElementById('app');
const bootstrap = window.__HELMDESK_WIDGET__;

if (mountElement && bootstrap) {
  createApp(WidgetRoot, bootstrap).mount(mountElement);
}
