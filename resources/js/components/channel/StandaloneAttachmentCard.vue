<!--
  文件说明：网站渠道独立访客端附件卡片，统一渲染图片预览和普通文件下载入口。
  - 图片走站内 ImagePreviewDialog 查看原图，避免跳出当前会话页。
  - 非图片附件保留新标签下载入口，浏览器走 Content-Disposition 处理。
-->
<script setup lang="ts">
import ImagePreviewDialog from '@/components/common/ImagePreviewDialog.vue';
import { formatFileSize } from '@/lib/format';
import { computed, ref } from 'vue';

type AttachmentCardVariant = 'visitor' | 'assistant';

interface CardAttachment {
  id: string;
  name: string;
  mime_type: string;
  byte_size: number;
  url: string;
}

const props = withDefaults(
  defineProps<{
    attachment: CardAttachment;
    variant?: AttachmentCardVariant;
  }>(),
  {
    variant: 'assistant',
  },
);

const isImage = computed(() => props.attachment.mime_type.startsWith('image/'));

// 统一用中性色边框，附件在气泡内/外都能清晰看到边界。
const cardClass = computed(
  () =>
    'block overflow-hidden rounded-md border border-muted-foreground/30 bg-background/70',
);

const sizeClass = computed(() =>
  props.variant === 'visitor'
    ? 'mt-0.5 opacity-80'
    : 'mt-0.5 text-muted-foreground',
);

const previewOpen = ref(false);
const previewImages = computed(() => [props.attachment]);
</script>

<template>
  <button
    v-if="isImage"
    type="button"
    :class="[cardClass, 'cursor-zoom-in text-left']"
    @click="previewOpen = true"
  >
    <img
      :src="attachment.url"
      :alt="attachment.name"
      class="max-h-56 w-full object-contain"
    />
  </button>
  <a
    v-else
    :href="attachment.url"
    target="_blank"
    rel="noopener noreferrer"
    :class="cardClass"
  >
    <div class="px-3 py-2 text-xs">
      <div class="font-medium">{{ attachment.name }}</div>
      <div :class="sizeClass">
        {{ formatFileSize(attachment.byte_size) }}
      </div>
    </div>
  </a>

  <ImagePreviewDialog
    v-if="isImage"
    v-model:open="previewOpen"
    :images="previewImages"
  />
</template>
