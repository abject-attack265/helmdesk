/**
 * 文件说明：组合应用内容页与个人设置页的持久布局。
 */
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/SettingsLayout.vue';
import type { LayoutCallback } from '@inertiajs/vue3';

export const appContentLayout: LayoutCallback = () => [
  AppLayout,
  { hideHeader: true },
];

export const settingsPageLayout: LayoutCallback = () => [
  AppLayout,
  SettingsLayout,
];
