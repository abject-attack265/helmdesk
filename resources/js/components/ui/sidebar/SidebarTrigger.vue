<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { PanelLeft } from "@lucide/vue"
import { computed } from "vue"
import { useI18n } from "@/composables/useI18n"
import { cn } from "@/lib/utils"
import { Button } from '@/components/ui/button'
import { useSidebar } from "./utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { t } = useI18n()
const { isMobile, open, openMobile, toggleSidebar } = useSidebar()

const triggerLabel = computed(() => {
  if (isMobile.value) {
    return openMobile.value ? t("关闭导航") : t("打开导航")
  }

  return open.value ? t("收起侧边栏") : t("展开侧边栏")
})
</script>

<template>
  <Button
    type="button"
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon"
    :class="cn('size-9 md:size-7', props.class)"
    :aria-label="triggerLabel"
    :aria-expanded="isMobile ? openMobile : open"
    :title="triggerLabel"
    @click="toggleSidebar"
  >
    <PanelLeft />
  </Button>
</template>
