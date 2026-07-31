<script setup lang="ts">
import type { HTMLAttributes, Ref } from "vue"
import { router } from "@inertiajs/vue3"
import { defaultDocument, useEventListener, useMediaQuery, useVModel } from "@vueuse/core"
import { TooltipProvider } from "reka-ui"
import { computed, onUnmounted, ref, watch } from "vue"
import { cn } from "@/lib/utils"
import { provideSidebarContext, SIDEBAR_COOKIE_MAX_AGE, SIDEBAR_COOKIE_NAME, SIDEBAR_KEYBOARD_SHORTCUT, SIDEBAR_WIDTH, SIDEBAR_WIDTH_ICON } from "./utils"

const props = withDefaults(defineProps<{
  defaultOpen?: boolean
  open?: boolean
  class?: HTMLAttributes["class"]
}>(), {
  defaultOpen: !defaultDocument?.cookie.includes(`${SIDEBAR_COOKIE_NAME}=false`),
  open: undefined,
})

const emits = defineEmits<{
  "update:open": [open: boolean]
}>()

const isMobile = useMediaQuery("(width < 48rem)")
const openMobile = ref(false)

const open = useVModel(props, "open", emits, {
  defaultValue: props.defaultOpen ?? false,
  passive: (props.open === undefined) as false,
}) as Ref<boolean>

function setOpen(value: boolean) {
  open.value = value

  // 记住侧边栏展开状态，刷新后保持一致。
  document.cookie = `${SIDEBAR_COOKIE_NAME}=${open.value}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`
}

function setOpenMobile(value: boolean) {
  openMobile.value = value
}

function isEditableTarget(target: EventTarget | null): boolean {
  if (!(target instanceof Element)) {
    return false
  }

  return target.closest("input, textarea, select, [contenteditable]:not([contenteditable='false'])") !== null
}

function toggleSidebar() {
  return isMobile.value ? setOpenMobile(!openMobile.value) : setOpen(!open.value)
}

useEventListener("keydown", (event: KeyboardEvent) => {
  if (
    event.key === SIDEBAR_KEYBOARD_SHORTCUT
    && (event.metaKey || event.ctrlKey)
    && !isEditableTarget(event.target)
  ) {
    event.preventDefault()
    toggleSidebar()
  }
})

watch(isMobile, (mobile) => {
  if (!mobile) {
    setOpenMobile(false)
  }
})

const removeNavigateListener = typeof window !== "undefined"
  ? router.on("navigate", () => {
      setOpenMobile(false)
    })
  : undefined

onUnmounted(() => removeNavigateListener?.())

const state = computed(() => open.value ? "expanded" : "collapsed")

provideSidebarContext({
  state,
  open,
  setOpen,
  isMobile,
  openMobile,
  setOpenMobile,
  toggleSidebar,
})
</script>

<template>
  <TooltipProvider :delay-duration="0">
    <div
      data-slot="sidebar-wrapper"
      :style="{
        '--sidebar-width': SIDEBAR_WIDTH,
        '--sidebar-width-icon': SIDEBAR_WIDTH_ICON,
      }"
      :class="cn('group/sidebar-wrapper has-data-[variant=inset]:bg-sidebar flex min-h-svh w-full', props.class)"
      v-bind="$attrs"
    >
      <slot />
    </div>
  </TooltipProvider>
</template>
