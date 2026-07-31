/**
 * 文件说明：前端浏览器入口，初始化 Inertia、Vue 插件、主题和语言环境。
 */
import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, Fragment, h } from 'vue';
import Toaster from './components/ui/toast/Toaster.vue';
import { initializeTheme } from './composables/useAppearance';
import {
  forceSessionLocale,
  provideI18nState,
  requireLocale,
} from './composables/useI18n';
import { initializeTimezone } from './composables/useTimezone';
import type { Locale } from './locales';

const appName = 'HelmDesk';
let pageContextNavigationListenerSetup = false;

createInertiaApp({
  title: (title) => (title ? `${title} - ${appName}` : appName),
  resolve: (name) =>
    resolvePageComponent(
      `./pages/${name}.vue`,
      import.meta.glob<DefineComponent>('./pages/**/*.vue'),
    ),
  setup({ el, App, props, plugin }) {
    const initialProps = props.initialPage.props as {
      app_locale: Locale;
      auth?: {
        user?: {
          timezone?: string | null;
        } | null;
      };
    };

    forceSessionLocale(requireLocale(initialProps.app_locale, 'app_locale'));
    initializeTimezone(initialProps.auth?.user?.timezone);

    if (!pageContextNavigationListenerSetup) {
      router.on('navigate', (event) => {
        const pageProps = event.detail.page.props as {
          app_locale: Locale;
          auth?: {
            user?: {
              timezone?: string | null;
            } | null;
          };
        };

        forceSessionLocale(requireLocale(pageProps.app_locale, 'app_locale'));
        initializeTimezone(pageProps.auth?.user?.timezone);
      });
      pageContextNavigationListenerSetup = true;
    }

    // 根级 Toaster 的生命周期与浏览器应用一致。
    const vueApp = createApp({
      render: () => h(Fragment, null, [h(App, props), h(Toaster)]),
    });

    provideI18nState(vueApp);
    vueApp.use(plugin).mount(el);
  },
  progress: {
    color: '#4B5563',
  },
});

// 页面加载时恢复明暗主题。
initializeTheme();
