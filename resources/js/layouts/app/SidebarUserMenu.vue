<!--
  文件说明：应用侧边栏用户菜单，提供个人设置和退出入口。
-->
<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenuButton } from '@/components/ui/sidebar';
import { useI18n } from '@/composables/useI18n';
import { getInitials } from '@/composables/useInitials';
import type { AppPageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown, LogOut, Settings } from '@lucide/vue';
import { computed } from 'vue';

interface Props {
  profileHref: string;
  profileLabel: string;
  logoutHref: string;
  isMobile: boolean;
  sidebarState: string;
}

const props = defineProps<Props>();
const { t } = useI18n();
const page = usePage<AppPageProps>();

const user = computed(() => page.props.auth.user);
const showAvatar = computed(
  () => user.value.avatar && user.value.avatar !== '',
);

const handleLogout = () => {
  router.flushAll();
};

const dropdownSide = computed(() => {
  return props.isMobile
    ? 'bottom'
    : props.sidebarState === 'collapsed'
      ? 'left'
      : 'bottom';
});
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <SidebarMenuButton
        size="lg"
        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
        data-test="sidebar-menu-button"
      >
        <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
          <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
          <AvatarFallback class="rounded-lg text-black dark:text-white">
            {{ getInitials(user.name) }}
          </AvatarFallback>
        </Avatar>

        <div class="grid flex-1 text-left text-sm leading-tight">
          <span class="truncate font-medium">{{ user.name }}</span>
        </div>

        <ChevronsUpDown class="ml-auto size-4" />
      </SidebarMenuButton>
    </DropdownMenuTrigger>

    <DropdownMenuContent
      class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
      :side="dropdownSide"
      align="end"
      :side-offset="4"
    >
      <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
          <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
            <AvatarImage
              v-if="showAvatar"
              :src="user.avatar!"
              :alt="user.name"
            />
            <AvatarFallback class="rounded-lg text-black dark:text-white">
              {{ getInitials(user.name) }}
            </AvatarFallback>
          </Avatar>

          <div class="grid flex-1 text-left text-sm leading-tight">
            <span class="truncate font-medium">{{ user.name }}</span>
            <span class="truncate text-xs text-muted-foreground">{{
              user.email
            }}</span>
          </div>
        </div>
      </DropdownMenuLabel>

      <DropdownMenuSeparator />

      <DropdownMenuItem :as-child="true">
        <Link class="block w-full" :href="props.profileHref" as="button">
          <Settings class="mr-2 h-4 w-4" />
          {{ props.profileLabel }}
        </Link>
      </DropdownMenuItem>

      <DropdownMenuSeparator />

      <DropdownMenuItem :as-child="true">
        <Link
          class="block w-full"
          :href="props.logoutHref"
          method="post"
          @click="handleLogout"
          as="button"
          data-test="logout-button"
        >
          <LogOut class="mr-2 h-4 w-4" />
          {{ t('退出登录') }}
        </Link>
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
