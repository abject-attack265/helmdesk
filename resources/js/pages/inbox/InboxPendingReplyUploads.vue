<!--
  收件箱待发送附件组件消费本地上传批次状态，展示进度、失败状态和移除入口。
-->
<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { formatFileSize } from '@/lib/format';
import { Image as ImageIcon, Paperclip, X } from '@lucide/vue';

interface InboxPendingReplyAttachment {
  id: string;
  name: string;
  byteSize: number;
  previewUrl: string | null;
  progress: number;
  status: 'uploading' | 'uploaded' | 'failed';
  statusLabel: string | null;
}

interface InboxPendingReplyUpload {
  id: string;
  kind: 'file' | 'image';
  attachments: InboxPendingReplyAttachment[];
}

const props = defineProps<{
  uploads: InboxPendingReplyUpload[];
}>();

const emit = defineEmits<{
  remove: [uploadId: string];
}>();

const { t } = useI18n();

function attachmentStatusLabel(
  attachment: InboxPendingReplyAttachment,
): string {
  if (attachment.status === 'failed') {
    return attachment.statusLabel ?? t('上传失败');
  }

  return `${attachment.progress}%`;
}
</script>

<template>
  <div v-if="props.uploads.length > 0" class="mb-2 flex flex-wrap gap-2">
    <template v-for="pendingUpload in props.uploads" :key="pendingUpload.id">
      <div
        v-for="attachment in pendingUpload.attachments"
        :key="attachment.id"
        class="relative"
      >
        <img
          v-if="pendingUpload.kind === 'image' && attachment.previewUrl"
          :src="attachment.previewUrl"
          :alt="attachment.name"
          class="h-16 w-16 rounded-lg object-cover"
        />
        <div
          v-else-if="pendingUpload.kind === 'image'"
          class="flex h-16 w-16 items-center justify-center rounded-lg border bg-muted/40 text-muted-foreground"
        >
          <ImageIcon class="size-4" />
        </div>
        <div
          v-else
          class="flex h-16 max-w-40 items-center gap-2 rounded-lg border bg-background/60 px-2"
        >
          <Paperclip class="size-4 shrink-0 text-muted-foreground" />
          <div class="min-w-0 text-xs">
            <div class="truncate font-medium">
              {{ attachment.name }}
            </div>
            <div class="text-muted-foreground">
              {{ formatFileSize(attachment.byteSize) }}
            </div>
          </div>
        </div>
        <div
          v-if="attachment.status !== 'uploaded'"
          class="absolute inset-0 flex items-center justify-center rounded-lg bg-black/40 text-[11px] font-medium text-white"
        >
          {{ attachmentStatusLabel(attachment) }}
        </div>
        <button
          v-if="attachment.status === 'failed'"
          type="button"
          class="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-destructive text-white shadow-sm"
          :title="t('移除')"
          @click="emit('remove', pendingUpload.id)"
        >
          <X class="size-2.5" />
        </button>
      </div>
    </template>
  </div>
</template>
