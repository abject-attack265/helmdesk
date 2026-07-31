/**
 * 文件说明：网站渠道详情页右侧实时预览的 iframe 入口。
 * 仅挂载预览壳 ChannelPreviewRoot，渠道外观草稿由后台父页面通过同源 postMessage 注入，无需保存即可实时渲染。
 */
import '../css/app.css';

import { createApp } from 'vue';
import ChannelPreviewRoot from './channel/ChannelPreviewRoot.vue';
import { initializeTheme } from './composables/useAppearance';
import { initializeStandaloneLocale } from './standalone/i18n';

// 复用访客端的主题与语言初始化，保证预览与真实访客界面口径一致。
initializeTheme();
initializeStandaloneLocale();

const mountElement = document.getElementById('app');

if (mountElement) {
  createApp(ChannelPreviewRoot).mount(mountElement);
}
