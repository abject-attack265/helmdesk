<!--
  文件说明：个人设置页面的二级导航与内容布局。
-->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { toUrl, urlIsActive } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/settings/appearance';
import { edit as editLanguage } from '@/routes/settings/language';
import { edit as editNotifications } from '@/routes/settings/notifications';
import { edit as editPassword } from '@/routes/settings/password';
import { edit as editProfile } from '@/routes/settings/profile';
import { show } from '@/routes/settings/two-factor';
import type { NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { computed, nextTick, onMounted, useTemplateRef, watch } from 'vue';

defineOptions({ inheritAttrs: false });

const { t } = useI18n();
const page = usePage();
const isCompactSettingsNav = useMediaQuery('(width < 64rem)');
const settingsNavScroller = useTemplateRef<HTMLElement>('settingsNavScroller');

const sidebarNavItems = computed<NavItem[]>(() => [
  {
    title: t('个人资料'),
    href: editProfile(),
  },
  {
    title: t('密码'),
    href: editPassword(),
  },
  {
    title: t('两步验证'),
    href: show(),
  },
  {
    title: t('语言和时区'),
    href: editLanguage(),
  },
  {
    title: t('通知'),
    href: editNotifications(),
  },
  {
    title: t('外观'),
    href: editAppearance(),
  },
]);

const isNavItemActive = (item: NavItem): boolean =>
  urlIsActive(item.href, page.url, { mode: 'path' });

const revealActiveNavItem = async (): Promise<void> => {
  if (!isCompactSettingsNav.value) {
    return;
  }

  await nextTick();

  const activeItem = settingsNavScroller.value?.querySelector<HTMLElement>(
    '[aria-current="page"]',
  );

  activeItem?.scrollIntoView({
    behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
      ? 'auto'
      : 'smooth',
    block: 'nearest',
    inline: 'nearest',
  });
};

onMounted(revealActiveNavItem);
watch([() => page.url, isCompactSettingsNav], revealActiveNavItem);
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col lg:flex-row lg:items-start">
    <aside
      class="sticky top-0 z-20 w-full shrink-0 bg-background/95 backdrop-blur-sm lg:top-4 lg:z-10 lg:ml-4 lg:w-56"
    >
      <nav :aria-label="t('设置')" class="flex flex-col gap-3 px-4 pt-3 lg:p-4">
        <div class="hidden space-y-0.5 lg:block">
          <h2 class="text-xl font-semibold tracking-tight">
            {{ t('设置') }}
          </h2>
          <p class="text-sm text-muted-foreground">
            {{ t('管理你的个人资料和账户设置') }}
          </p>
        </div>

        <div
          ref="settingsNavScroller"
          class="-mx-4 [scrollbar-width:none] overflow-x-auto overscroll-x-contain px-4 pb-2 lg:mx-0 lg:overflow-visible lg:px-0 lg:pb-0 [&::-webkit-scrollbar]:hidden"
        >
          <div class="flex min-w-max gap-1 lg:min-w-0 lg:flex-col">
            <Button
              v-for="item in sidebarNavItems"
              :key="toUrl(item.href)"
              variant="ghost"
              :class="[
                'h-10 shrink-0 justify-start px-3 lg:h-9 lg:w-full',
                {
                  'bg-muted text-foreground': isNavItemActive(item),
                },
              ]"
              as-child
            >
              <Link
                :href="item.href"
                :aria-current="isNavItemActive(item) ? 'page' : undefined"
              >
                {{ item.title }}
              </Link>
            </Button>
          </div>
        </div>
      </nav>
    </aside>

    <div class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:py-8">
      <section class="mx-auto w-full max-w-none space-y-12">
        <slot />
      </section>
    </div>
  </div>
</template>
