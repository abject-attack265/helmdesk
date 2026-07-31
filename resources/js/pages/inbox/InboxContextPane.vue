<!--
  收件箱联系人资料栏消费 InboxSelectionData 和 TagOptionData，
  在桌面资料栏与移动抽屉间保留同一编辑实例。
-->
<script setup lang="ts">
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';
import { useI18n } from '@/composables/useI18n';
import { useToast } from '@/composables/useToast';
import InboxContextPanel from '@/pages/inbox/InboxContextPanel.vue';
import type { InboxSelectionData, TagOptionData } from '@/types/generated';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
  selection: InboxSelectionData | null;
  availableContactTags: TagOptionData[];
  targetLocale: string;
  translationEnabled: boolean;
  writeBlocked: boolean;
}>();

const emit = defineEmits<{
  'write-pending-change': [pending: boolean];
  'write-failed': [];
}>();

const mobileContextOpen = ref(false);
const mobilePanelTeleported = ref(false);
const mobileContextTarget = ref<HTMLElement | null>(null);
const contextWritePending = ref(false);
let mobileTransitionSequence = 0;
let mobileOpenRequested = false;
let mobileCloseRequested = false;
const contextPanelWidth = ref(380);
const contextPanelCollapsed = ref(false);
const CONTEXT_PANEL_MIN_WIDTH = 320;
const CONTEXT_PANEL_MAX_WIDTH = 640;
const CONTEXT_PANEL_TOGGLE_WIDTH_PX = 16;

const { t } = useI18n();
const { toast } = useToast();

const contextPanelStyle = computed(() => ({
  width: `${contextPanelWidth.value}px`,
}));

watch(contextWritePending, (pending) => emit('write-pending-change', pending), {
  immediate: true,
});

const contextPanelToggleClass = computed(() =>
  contextPanelCollapsed.value
    ? 'rounded-l-md border-r-0'
    : 'rounded-r-md border-l-0',
);

function clampContextPanelWidth(width: number): number {
  return Math.min(
    CONTEXT_PANEL_MAX_WIDTH,
    Math.max(CONTEXT_PANEL_MIN_WIDTH, width),
  );
}

function stopResizeContextPanel(): void {
  window.removeEventListener('pointermove', resizeContextPanel);
  window.removeEventListener('pointerup', stopResizeContextPanel);
  window.removeEventListener('pointercancel', stopResizeContextPanel);
}

function resizeContextPanel(event: PointerEvent): void {
  contextPanelWidth.value = clampContextPanelWidth(
    window.innerWidth - event.clientX - CONTEXT_PANEL_TOGGLE_WIDTH_PX,
  );
}

function startResizeContextPanel(event: PointerEvent): void {
  if (contextPanelCollapsed.value) {
    return;
  }

  event.preventDefault();
  window.addEventListener('pointermove', resizeContextPanel);
  window.addEventListener('pointerup', stopResizeContextPanel);
  window.addEventListener('pointercancel', stopResizeContextPanel);
}

function toggleContextPanel(): void {
  contextPanelCollapsed.value = !contextPanelCollapsed.value;
}

async function setMobileContextOpen(open: boolean): Promise<void> {
  const sequence = ++mobileTransitionSequence;
  if (!open) {
    mobilePanelTeleported.value = false;
    mobileContextOpen.value = false;

    return;
  }

  mobileContextOpen.value = true;
  await nextTick();
  if (sequence !== mobileTransitionSequence || !mobileContextOpen.value) {
    return;
  }
  if (mobileContextTarget.value === null) {
    mobileContextOpen.value = false;
    console.warn('[inbox-context] 移动联系人资料容器未挂载', {
      conversationId: props.selection?.conversation.id ?? null,
      contactId: props.selection?.contact.id ?? null,
    });
    toast.error(t('联系人资料面板打开失败，请重试'));

    return;
  }

  mobilePanelTeleported.value = true;
}

function openMobile(): void {
  if (!props.selection) {
    throw new Error('当前没有可展示的联系人资料');
  }
  if (contextWritePending.value) {
    mobileOpenRequested = true;
    console.info('[inbox-context] 等待联系人资料保存后打开移动面板', {
      conversationId: props.selection.conversation.id,
      contactId: props.selection.contact.id,
    });

    return;
  }

  mobileOpenRequested = false;
  void setMobileContextOpen(true);
}

function updateMobileContextOpen(open: boolean): void {
  if (open && contextWritePending.value) {
    mobileOpenRequested = true;

    return;
  }
  if (!open && contextWritePending.value) {
    mobileCloseRequested = true;
    console.info('[inbox-context] 等待联系人资料保存后关闭移动面板', {
      conversationId: props.selection?.conversation.id ?? null,
      contactId: props.selection?.contact.id ?? null,
    });

    return;
  }

  mobileOpenRequested = false;
  mobileCloseRequested = false;
  void setMobileContextOpen(open);
}

/** 保存失败时保留移动面板，以便用户查看并修正字段错误。 */
function handleWriteFailure(): void {
  const shouldOpenMobile = mobileOpenRequested;
  mobileOpenRequested = false;
  mobileCloseRequested = false;
  if (shouldOpenMobile) {
    void setMobileContextOpen(true);
    console.info('[inbox-context] 打开移动面板以显示资料保存错误', {
      conversationId: props.selection?.conversation.id ?? null,
      contactId: props.selection?.contact.id ?? null,
    });
  }
  emit('write-failed');
}

defineExpose({ openMobile });

watch(contextWritePending, (pending) => {
  if (pending) {
    return;
  }
  if (mobileCloseRequested) {
    mobileCloseRequested = false;
    void setMobileContextOpen(false);
  } else if (mobileOpenRequested) {
    mobileOpenRequested = false;
    void setMobileContextOpen(true);
  }
});

watch(
  () => props.selection?.conversation.id ?? null,
  () => {
    mobileOpenRequested = false;
    mobileCloseRequested = false;
    mobileTransitionSequence += 1;
    mobilePanelTeleported.value = false;
    mobileContextOpen.value = false;
    contextWritePending.value = false;
    stopResizeContextPanel();
  },
);

onUnmounted(() => {
  mobileTransitionSequence += 1;
  stopResizeContextPanel();
});
</script>

<template>
  <template v-if="props.selection">
    <div
      class="relative hidden min-h-0 w-4 shrink-0 bg-background lg:block"
      :class="{ 'border-l': !contextPanelCollapsed }"
    >
      <button
        v-if="!contextPanelCollapsed"
        type="button"
        class="absolute top-0 left-0 z-20 h-full w-2 -translate-x-1 cursor-col-resize touch-none"
        :aria-label="t('调整资料栏宽度')"
        @pointerdown="startResizeContextPanel"
      />
      <button
        type="button"
        class="absolute top-1/2 left-0 z-30 flex h-12 w-4 -translate-y-1/2 items-center justify-center border border-border bg-muted text-muted-foreground shadow-sm transition-colors hover:bg-muted/80 hover:text-foreground"
        :class="contextPanelToggleClass"
        :title="contextPanelCollapsed ? t('展开资料栏') : t('收起资料栏')"
        :aria-label="contextPanelCollapsed ? t('展开资料栏') : t('收起资料栏')"
        @click="toggleContextPanel"
      >
        <ChevronLeft v-if="contextPanelCollapsed" class="size-3" />
        <ChevronRight v-else class="size-3" />
      </button>
    </div>

    <div
      v-show="!contextPanelCollapsed"
      class="relative hidden h-full min-h-0 min-w-0 shrink-0 bg-background lg:flex"
      :style="contextPanelStyle"
    >
      <div class="h-full min-h-0 min-w-0 flex-1">
        <Teleport
          :to="mobilePanelTeleported ? mobileContextTarget : null"
          :disabled="!mobilePanelTeleported"
        >
          <InboxContextPanel
            :contact-profile="props.selection.contact_profile"
            :conversation="props.selection.conversation"
            :available-contact-tags="props.availableContactTags"
            :target-locale="props.targetLocale"
            :can-translate="props.selection.can_translate_messages"
            :translation-enabled="props.translationEnabled"
            :write-blocked="props.writeBlocked"
            @write-pending-change="contextWritePending = $event"
            @write-failed="handleWriteFailure"
          />
        </Teleport>
      </div>
    </div>

    <Sheet :open="mobileContextOpen" @update:open="updateMobileContextOpen">
      <SheetContent side="right" class="w-full gap-0 p-0 sm:max-w-sm">
        <SheetHeader class="sr-only">
          <SheetTitle>{{ t('客户资料') }}</SheetTitle>
        </SheetHeader>
        <div ref="mobileContextTarget" class="min-h-0 flex-1 overflow-hidden" />
      </SheetContent>
    </Sheet>
  </template>
</template>
