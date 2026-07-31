<!--
  文件说明：应用后台侧边栏外壳，渲染分组导航、账号菜单和内容区。
-->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  SidebarProvider,
  SidebarTrigger,
} from '@/components/ui/sidebar';
import { useI18n } from '@/composables/useI18n';
import SidebarContextConsumer from '@/layouts/app/SidebarContextConsumer.vue';
import SidebarUserMenu from '@/layouts/app/SidebarUserMenu.vue';
import { cn, toUrl, urlIsActive } from '@/lib/utils';
import type { NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight, Pin, X } from '@lucide/vue';
import { reactive } from 'vue';

export type SidebarShellNavItem = NavItem & {
  activeUrls?: string[];
  /**
   * 二级菜单项；存在时该项渲染为可折叠菜单，子项激活时默认展开。
   */
  children?: SidebarShellNavItem[];
};

interface Props {
  hideHeader?: boolean;
  headerHref: string;
  headerTitle: string;
  headerLogoUrl: string;
  headerLogoInvertsInDark?: boolean;
  mainNavItems: SidebarShellNavItem[];
  footerNavItems: NavItem[];
  profileHref: string;
  profileLabel: string;
  logoutHref: string;
}

const props = withDefaults(defineProps<Props>(), {
  hideHeader: false,
});

const page = usePage();
const { t } = useI18n();
const isOpen = Boolean(page.props.sidebarOpen);
const persistedOpenGroups = reactive<Record<string, boolean>>({});

const isExternalLink = (href: NavItem['href']) => {
  const url = toUrl(href);
  return url.startsWith('http://') || url.startsWith('https://');
};

const isMainNavItemActive = (item: SidebarShellNavItem) => {
  if (item.activeUrls && item.activeUrls.length > 0) {
    return item.activeUrls.some((u) =>
      urlIsActive(u, page.url, { mode: 'prefix' }),
    );
  }

  return urlIsActive(item.href, page.url);
};

const groupStateKey = (item: SidebarShellNavItem): string => toUrl(item.href);

// 激活分组首次渲染时展开，后续以当前布局实例中的用户选择为准。
for (const item of props.mainNavItems) {
  if (
    item.children &&
    item.children.length > 0 &&
    persistedOpenGroups[groupStateKey(item)] === undefined
  ) {
    persistedOpenGroups[groupStateKey(item)] = isMainNavItemActive(item);
  }
}

const isGroupOpen = (item: SidebarShellNavItem) =>
  persistedOpenGroups[groupStateKey(item)] ?? false;

const setGroupOpen = (item: SidebarShellNavItem, open: boolean) => {
  persistedOpenGroups[groupStateKey(item)] = open;
};

const sidebarToggleLabel = (
  mobile: boolean,
  sidebarState: 'expanded' | 'collapsed',
) => {
  if (mobile) {
    return t('关闭导航');
  }

  return sidebarState === 'expanded' ? t('收起侧边栏') : t('展开侧边栏');
};
</script>

<template>
  <SidebarProvider :default-open="isOpen">
    <Sidebar collapsible="icon" variant="inset">
      <SidebarContextConsumer v-slot="{ toggleSidebar, state, isMobile }">
        <SidebarHeader class="group-data-[collapsible=icon]:p-0!">
          <div
            class="flex items-center justify-between group-data-[collapsible=icon]:flex-col group-data-[collapsible=icon]:gap-2"
          >
            <SidebarMenu class="w-full group-data-[collapsible=icon]:p-2!">
              <SidebarMenuItem>
                <div
                  class="flex w-full items-center gap-2 px-0 py-0 group-data-[collapsible=icon]:flex-col group-data-[collapsible=icon]:items-center"
                >
                  <Link
                    :href="props.headerHref"
                    class="shrink-0 p-2 group-data-[collapsible=icon]:p-0"
                    :aria-label="t('前往仪表板')"
                  >
                    <div
                      class="flex aspect-square size-12 items-center justify-center overflow-hidden rounded-md bg-foreground text-background"
                    >
                      <img
                        :src="props.headerLogoUrl"
                        :alt="props.headerTitle"
                        :class="
                          cn(
                            'h-full w-full object-contain',
                            props.headerLogoInvertsInDark && 'dark:invert',
                          )
                        "
                      />
                    </div>
                  </Link>

                  <div
                    class="flex min-w-0 flex-1 flex-col gap-1 pr-2 group-data-[collapsible=icon]:hidden"
                  >
                    <span class="text-sm leading-tight font-semibold">
                      {{ props.headerTitle }}
                    </span>
                  </div>
                </div>
              </SidebarMenuItem>
            </SidebarMenu>

            <Button
              type="button"
              variant="ghost"
              size="icon"
              :aria-label="sidebarToggleLabel(isMobile.value, state.value)"
              :title="sidebarToggleLabel(isMobile.value, state.value)"
              :class="
                cn(
                  'size-9 shrink-0 transition-colors duration-200 md:size-7',
                  'group-data-[collapsible=icon]:mr-0 group-data-[collapsible=icon]:mb-2',
                  'group-data-[state=expanded]/sidebar-wrapper:bg-sidebar-accent group-data-[state=expanded]/sidebar-wrapper:text-sidebar-accent-foreground',
                )
              "
              @click="toggleSidebar"
            >
              <X v-if="isMobile.value" class="size-4" />
              <Pin
                v-else
                :class="
                  cn(
                    'h-4 w-4 transition-all duration-200',
                    'group-data-[state=collapsed]/sidebar-wrapper:rotate-45',
                  )
                "
                :fill="state.value === 'expanded' ? 'currentColor' : 'none'"
              />
            </Button>
          </div>
        </SidebarHeader>

        <SidebarContent>
          <SidebarGroup class="px-2 py-0">
            <SidebarMenu>
              <template v-for="item in props.mainNavItems" :key="item.title">
                <Collapsible
                  v-if="item.children && item.children.length > 0"
                  as-child
                  :open="isGroupOpen(item)"
                  @update:open="(open) => setGroupOpen(item, open)"
                >
                  <SidebarMenuItem>
                    <!-- 图标收起模式下子菜单不可见，父项直接导航到默认子页 -->
                    <SidebarMenuButton
                      v-if="state.value === 'collapsed' && !isMobile.value"
                      as-child
                      :is-active="isMainNavItemActive(item)"
                      :tooltip="item.title"
                    >
                      <Link
                        :href="toUrl(item.href)"
                        :aria-current="
                          isMainNavItemActive(item) ? 'page' : undefined
                        "
                      >
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                      </Link>
                    </SidebarMenuButton>

                    <!-- 展开模式下父项整行作为折叠开关，导航交给子项 -->
                    <CollapsibleTrigger v-else as-child>
                      <SidebarMenuButton
                        :is-active="
                          isMainNavItemActive(item) && !isGroupOpen(item)
                        "
                      >
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                        <ChevronRight
                          :class="
                            cn(
                              'ml-auto transition-transform duration-200',
                              isGroupOpen(item) && 'rotate-90',
                            )
                          "
                        />
                      </SidebarMenuButton>
                    </CollapsibleTrigger>

                    <CollapsibleContent
                      class="overflow-hidden data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down"
                    >
                      <SidebarMenuSub>
                        <SidebarMenuSubItem
                          v-for="child in item.children"
                          :key="child.title"
                        >
                          <SidebarMenuSubButton
                            as-child
                            :is-active="isMainNavItemActive(child)"
                          >
                            <Link
                              :href="toUrl(child.href)"
                              :aria-current="
                                isMainNavItemActive(child) ? 'page' : undefined
                              "
                            >
                              <span>{{ child.title }}</span>
                            </Link>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                      </SidebarMenuSub>
                    </CollapsibleContent>
                  </SidebarMenuItem>
                </Collapsible>

                <SidebarMenuItem v-else>
                  <SidebarMenuButton
                    as-child
                    :is-active="isMainNavItemActive(item)"
                    :tooltip="item.title"
                  >
                    <Link
                      :href="toUrl(item.href)"
                      :aria-current="
                        isMainNavItemActive(item) ? 'page' : undefined
                      "
                    >
                      <component :is="item.icon" />
                      <span>{{ item.title }}</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              </template>
            </SidebarMenu>
          </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
          <SidebarGroup class="group-data-[collapsible=icon]:p-0">
            <SidebarGroupContent>
              <SidebarMenu>
                <SidebarMenuItem
                  v-for="item in props.footerNavItems"
                  :key="item.title"
                >
                  <SidebarMenuButton
                    class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                    as-child
                  >
                    <a
                      v-if="isExternalLink(item.href)"
                      :href="toUrl(item.href)"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <component :is="item.icon" />
                      <span>{{ item.title }}</span>
                    </a>
                    <Link v-else :href="toUrl(item.href)">
                      <component :is="item.icon" />
                      <span>{{ item.title }}</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>

          <SidebarMenu>
            <SidebarMenuItem>
              <slot
                name="userMenu"
                :isMobile="isMobile.value"
                :sidebarState="state.value"
              >
                <SidebarUserMenu
                  :profile-href="props.profileHref"
                  :profile-label="props.profileLabel"
                  :logout-href="props.logoutHref"
                  :is-mobile="isMobile.value"
                  :sidebar-state="state.value"
                />
              </slot>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarFooter>
      </SidebarContextConsumer>
    </Sidebar>

    <SidebarInset class="overflow-x-hidden">
      <header
        v-if="!props.hideHeader"
        class="flex h-12 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-4 md:hidden"
      >
        <SidebarTrigger class="-ml-2 size-10" />
      </header>

      <slot />
    </SidebarInset>
  </SidebarProvider>
</template>
