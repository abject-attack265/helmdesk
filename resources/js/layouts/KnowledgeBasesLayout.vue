<!--
  知识库管理布局，在桌面端和移动端展示知识库与分组导航。
-->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useI18n } from '@/composables/useI18n';
import { Library, X } from '@lucide/vue';
import { ref } from 'vue';

defineProps<{
  contentClass?: string;
}>();

defineSlots<{
  sidebar?(props: { closeMobileExplorer: () => void }): unknown;
  default?(): unknown;
}>();

const { t } = useI18n();
const mobileExplorerOpen = ref(false);

function closeMobileExplorer(): void {
  mobileExplorerOpen.value = false;
}
</script>

<template>
  <div class="flex min-w-0 flex-1 flex-col lg:flex-row">
    <aside
      v-if="$slots.sidebar"
      class="hidden w-full lg:block lg:w-68 lg:shrink-0 lg:self-stretch"
    >
      <nav
        class="flex h-full flex-col border-r border-border/40 bg-card/50 shadow-sm backdrop-blur-sm"
      >
        <div class="space-y-0.5 px-6 pt-6 pb-4">
          <h2 class="text-base font-medium">
            {{ t('知识库') }}
          </h2>
          <p class="text-sm text-muted-foreground">
            {{ t('管理知识库中的文档、问答和分组') }}
          </p>
        </div>

        <div class="flex min-h-0 flex-1 flex-col">
          <slot name="sidebar" :close-mobile-explorer="closeMobileExplorer" />
        </div>
      </nav>
    </aside>

    <div
      class="flex h-12 shrink-0 items-center gap-1 border-b border-sidebar-border/70 px-4 lg:hidden"
      :class="{ 'md:hidden': !$slots.sidebar }"
    >
      <SidebarTrigger class="-ml-2 size-10 md:hidden" />
      <span v-if="!$slots.sidebar" class="truncate text-sm font-medium">
        {{ t('知识库') }}
      </span>

      <template v-if="$slots.sidebar">
        <div class="mx-1 h-5 w-px bg-border md:hidden" aria-hidden="true" />
        <Sheet v-model:open="mobileExplorerOpen">
          <SheetTrigger as-child>
            <Button
              variant="ghost"
              size="sm"
              class="h-9 min-w-0 gap-2 px-2"
              :aria-expanded="mobileExplorerOpen"
            >
              <Library class="size-4 shrink-0" />
              <span class="truncate">{{ t('知识库') }}</span>
            </Button>
          </SheetTrigger>
          <SheetContent
            side="left"
            class="h-dvh max-h-dvh w-[min(88vw,20rem)] gap-0 p-0 [&>button]:hidden"
          >
            <SheetHeader
              class="flex-row items-start justify-between gap-3 border-b px-4 py-4 text-left"
            >
              <div class="min-w-0 space-y-0.5">
                <SheetTitle class="text-base font-medium">
                  {{ t('知识库') }}
                </SheetTitle>
                <SheetDescription>
                  {{ t('管理知识库中的文档、问答和分组') }}
                </SheetDescription>
              </div>
              <SheetClose as-child>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="size-8 shrink-0"
                  :aria-label="t('关闭')"
                  :title="t('关闭')"
                >
                  <X class="size-4" />
                </Button>
              </SheetClose>
            </SheetHeader>
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
              <slot
                name="sidebar"
                :close-mobile-explorer="closeMobileExplorer"
              />
            </div>
          </SheetContent>
        </Sheet>
      </template>
    </div>

    <div class="min-w-0 flex-1 px-4 py-6 sm:px-6">
      <section :class="['mx-auto w-full', contentClass ?? 'max-w-none']">
        <slot />
      </section>
    </div>
  </div>
</template>
