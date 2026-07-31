/**
 * 文件说明：前端 SSR 入口，负责在服务端渲染 Inertia 页面。
 */
import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import {
  createI18nState,
  provideI18nState,
  requireLocale,
} from './composables/useI18n';
import type { Locale } from './locales';

const appName = 'HelmDesk';

createServer(
  (page) =>
    createInertiaApp({
      page,
      render: renderToString,
      title: (title) => (title ? `${title} - ${appName}` : appName),
      resolve: (name) =>
        resolvePageComponent(
          `./pages/${name}.vue`,
          import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
      setup: ({ App, props, plugin }) => {
        const initialProps = props.initialPage.props as {
          app_locale: Locale;
        };
        const vueApp = createSSRApp({ render: () => h(App, props) });

        provideI18nState(
          vueApp,
          createI18nState(requireLocale(initialProps.app_locale, 'app_locale')),
        );

        return vueApp.use(plugin);
      },
    }),
  { cluster: true },
);
