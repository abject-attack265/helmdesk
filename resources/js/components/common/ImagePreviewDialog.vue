<!--
  文件说明：图片附件站内预览对话框，承载客服与访客点击附件后查看原图的交互。
  - 复用 @/components/ui/dialog（Reka UI 头层），自带 Esc 关闭、focus trap、点击 overlay 关闭与 a11y。
  - 支持单图或多图（左右键 / 屏幕按钮翻页）。
-->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import { formatFileSize } from '@/lib/format';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { VisuallyHidden } from 'reka-ui';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

interface PreviewImage {
  id: string;
  name: string;
  mime_type: string;
  byte_size: number;
  url: string;
}

const props = defineProps<{
  open: boolean;
  images: PreviewImage[];
  initialId?: string | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
}>();

const { t } = useI18n();

const activeIndex = ref(0);
const activeImage = computed(() => props.images[activeIndex.value] ?? null);
const hasMultiple = computed(() => props.images.length > 1);

watch(
  () => [props.open, props.initialId, props.images.length],
  ([open]) => {
    if (!open) return;
    const idx = props.initialId
      ? props.images.findIndex((img) => img.id === props.initialId)
      : 0;
    activeIndex.value = idx >= 0 ? idx : 0;
  },
  { immediate: true },
);

function step(delta: number): void {
  if (!hasMultiple.value) return;
  const total = props.images.length;
  activeIndex.value = (activeIndex.value + delta + total) % total;
}

function onKeydown(event: KeyboardEvent): void {
  if (!props.open || !hasMultiple.value) return;
  if (event.key === 'ArrowLeft') {
    event.preventDefault();
    step(-1);
  } else if (event.key === 'ArrowRight') {
    event.preventDefault();
    step(1);
  }
}

watch(
  () => props.open,
  (open) => {
    if (typeof window === 'undefined') return;
    if (open) {
      window.addEventListener('keydown', onKeydown);
    } else {
      window.removeEventListener('keydown', onKeydown);
    }
  },
  { immediate: true },
);

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', onKeydown);
  }
});
</script>

<template>
  <Dialog :open="open" @update:open="emit('update:open', $event)">
    <DialogContent
      class="flex max-h-[92vh] w-[min(92vw,1100px)] max-w-none flex-col gap-3 border-none bg-background/95 p-3 sm:max-w-none"
    >
      <VisuallyHidden>
        <DialogTitle>{{ activeImage?.name ?? t('图片预览') }}</DialogTitle>
        <DialogDescription>
          {{ t('图片附件预览，按 Esc 关闭。') }}
        </DialogDescription>
      </VisuallyHidden>

      <div
        class="relative flex flex-1 items-center justify-center overflow-hidden rounded-md bg-black/40"
      >
        <img
          v-if="activeImage"
          :key="activeImage.id"
          :src="activeImage.url"
          :alt="activeImage.name"
          class="max-h-[80vh] max-w-full object-contain select-none"
          draggable="false"
        />

        <Button
          v-if="hasMultiple"
          variant="secondary"
          size="icon"
          class="absolute top-1/2 left-3 -translate-y-1/2 rounded-full opacity-90 hover:opacity-100"
          :aria-label="t('上一张')"
          @click="step(-1)"
        >
          <ChevronLeft class="size-5" />
        </Button>

        <Button
          v-if="hasMultiple"
          variant="secondary"
          size="icon"
          class="absolute top-1/2 right-3 -translate-y-1/2 rounded-full opacity-90 hover:opacity-100"
          :aria-label="t('下一张')"
          @click="step(1)"
        >
          <ChevronRight class="size-5" />
        </Button>
      </div>

      <div v-if="activeImage" class="text-xs text-muted-foreground">
        <div class="truncate text-foreground">{{ activeImage.name }}</div>
        <div class="mt-0.5">
          {{ formatFileSize(activeImage.byte_size) }}
          <span v-if="hasMultiple" class="ml-2">
            {{ activeIndex + 1 }} / {{ images.length }}
          </span>
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
