<!--
  文件说明：访客端聊天画布中的引用消息预览。
  C 端气泡上方需要展示「谁说了什么」的引用条；按消息归属（访客/客服）
  仅在左右侧边线和对齐上有差别，这里把图片 / 文件 / 文本三种分支统一成一个组件。
-->
<script setup lang="ts">
import { useStandaloneI18n } from '@/standalone/i18n';
import type { ReceptionMessageData } from '@/types/generated';
import { Paperclip } from '@lucide/vue';
import { computed } from 'vue';

type ReceptionAttachment = ReceptionMessageData['attachments'][number];

interface QuotedLike {
  preview: string;
  content: string | null;
  attachments: unknown;
  role?: string | null;
  sender_name?: string | null;
  senderName?: string | null;
}

const props = defineProps<{
  quoted: QuotedLike;
  side: 'visitor' | 'assistant';
}>();

const emit = defineEmits<{
  (e: 'open'): void;
}>();

const { t } = useStandaloneI18n();

// 引用消息每行最大宽度（单位 ch；CJK 字符按 2 个宽度单位计）。
const QUOTED_MAX_WIDTH_CH = 32;
const QUOTED_MIN_BALANCED_WIDTH_CH = 18;
const QUOTED_FULL_WIDTH_THRESHOLD_CH = QUOTED_MAX_WIDTH_CH * 2.4;
const QUOTED_BALANCE_RATIO = 2.18;

const senderName = computed(() => {
  if (props.quoted.role === 'visitor') {
    return t('你');
  }
  return props.quoted.senderName ?? props.quoted.sender_name ?? '';
});

const attachments = computed<ReceptionAttachment[]>(() => {
  const value = props.quoted.attachments;
  if (Array.isArray(value)) {
    return value as ReceptionAttachment[];
  }
  if (value && typeof value === 'object') {
    return Object.values(value) as ReceptionAttachment[];
  }

  return [];
});

const imageAttachment = computed<ReceptionAttachment | null>(
  () =>
    attachments.value.find((attachment) =>
      attachment.mime_type.startsWith('image/'),
    ) ?? null,
);

const fileAttachment = computed<ReceptionAttachment | null>(
  () =>
    attachments.value.find(
      (attachment) => !attachment.mime_type.startsWith('image/'),
    ) ?? null,
);

function displayWidth(text: string): number {
  let width = 0;

  for (const char of text) {
    width += /[^\u0000-\u00ff]/u.test(char) ? 2 : 1;
  }

  return width;
}

const previewWidthStyle = computed<Record<string, string>>(() => {
  const label = `${senderName.value}：${props.quoted.preview}`;
  const width = displayWidth(label);

  if (width <= QUOTED_MAX_WIDTH_CH) {
    return { width: 'fit-content' };
  }

  if (width >= QUOTED_FULL_WIDTH_THRESHOLD_CH) {
    return { width: `${QUOTED_MAX_WIDTH_CH}ch` };
  }

  return {
    width: `${Math.min(
      QUOTED_MAX_WIDTH_CH,
      Math.max(
        QUOTED_MIN_BALANCED_WIDTH_CH,
        Math.ceil(width / QUOTED_BALANCE_RATIO),
      ),
    )}ch`,
  };
});

// 访客气泡里引用条挨在气泡右侧（border-r），客服气泡里挨在左侧（border-l）。
const borderClass = computed(() =>
  props.side === 'visitor' ? 'border-r-2 pr-2' : 'border-l-2 pl-2 self-start',
);

function handleOpen(): void {
  emit('open');
}
</script>

<template>
  <button
    v-if="imageAttachment"
    type="button"
    class="flex items-center gap-2 border-muted-foreground/30 text-xs text-muted-foreground/70 transition-colors hover:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
    :class="borderClass"
    @click.stop="handleOpen"
  >
    <span class="font-medium">{{ senderName }}：</span>
    <img
      :src="imageAttachment.url"
      :alt="imageAttachment.name"
      class="size-12 rounded-md object-cover"
    />
  </button>
  <button
    v-else-if="fileAttachment"
    type="button"
    class="flex items-center gap-2 border-muted-foreground/30 text-xs text-muted-foreground/70 transition-colors hover:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
    :class="borderClass"
    @click.stop="handleOpen"
  >
    <span class="font-medium">{{ senderName }}：</span>
    <span
      class="inline-flex max-w-[20ch] items-center gap-1 rounded-md bg-muted/60 px-2 py-1"
    >
      <Paperclip class="size-3 shrink-0" />
      <span class="truncate">{{ fileAttachment.name }}</span>
    </span>
  </button>
  <button
    v-else
    type="button"
    class="border-muted-foreground/30 text-xs text-muted-foreground/70 transition-colors hover:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
    :class="borderClass"
    @click.stop="handleOpen"
  >
    <span
      class="line-clamp-2 max-w-[32ch] text-left leading-5 [text-wrap:balance] [overflow-wrap:anywhere] break-all whitespace-normal"
      :style="previewWidthStyle"
    >
      <span class="font-medium">{{ senderName }}： </span>{{ quoted.preview }}
    </span>
  </button>
</template>
