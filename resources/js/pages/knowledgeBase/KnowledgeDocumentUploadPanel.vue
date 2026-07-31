<!--
  知识库文档上传面板，支持选择或拖入文件，并展示上传进度与失败重试入口。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { formatFileSize } from '@/lib/format';
import axios, { AxiosError } from 'axios';
import {
  Check,
  CircleAlert,
  FileText,
  LoaderCircle,
  RotateCw,
  UploadCloud,
  X,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';

type UploadStatus = 'pending' | 'uploading' | 'success' | 'failed';

interface UploadItem {
  id: string;
  file: File;
  status: UploadStatus;
  progress: number;
  error: string | null;
}

const props = defineProps<{
  knowledgeBaseId: string;
  groupId: string | null;
  showHeading?: boolean;
}>();

const emit = defineEmits<{
  cancel: [];
  completed: [];
  'update:processing': [processing: boolean];
}>();

const { t } = useI18n();

const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024;
const MAX_FILE_SIZE_LABEL = `${MAX_FILE_SIZE_BYTES / 1024 / 1024} MB`;
const MAX_FILE_COUNT = 20;
const ALLOWED_EXTENSIONS = [
  'md',
  'markdown',
  'txt',
  'pdf',
  'docx',
  'html',
  'htm',
];
const ACCEPT_ATTRIBUTE = ALLOWED_EXTENSIONS.map((ext) => `.${ext}`).join(',');
const allowedFileTypesLabel = computed(() =>
  t('Word、PDF、TXT、Markdown 和 HTML'),
);

const fileInputRef = ref<HTMLInputElement | null>(null);
const isDraggingOver = ref(false);
const localHint = ref<string | null>(null);
const items = ref<UploadItem[]>([]);
const isProcessing = ref(false);

defineExpose({ isProcessing });

let nextItemId = 0;
function makeItemId(): string {
  nextItemId += 1;
  return `upload-${Date.now()}-${nextItemId}`;
}

const totalSize = computed(() =>
  items.value.reduce((sum, item) => sum + item.file.size, 0),
);

const hasFailedItems = computed(() =>
  items.value.some((item) => item.status === 'failed'),
);

onMounted(() => {
  resetState();
  emit('update:processing', false);
});

function resetState(): void {
  if (isProcessing.value) {
    return;
  }
  items.value = [];
  localHint.value = null;
  isDraggingOver.value = false;
}

function getExtension(file: File): string {
  const idx = file.name.lastIndexOf('.');
  if (idx < 0) {
    return '';
  }
  return file.name.slice(idx + 1).toLowerCase();
}

function isAllowedFile(file: File): boolean {
  return ALLOWED_EXTENSIONS.includes(getExtension(file));
}

function dedupeKey(file: File): string {
  return `${file.name}::${file.size}::${file.lastModified}`;
}

function appendFiles(incoming: FileList | File[] | null): void {
  if (!incoming) {
    return;
  }

  const list = Array.from(incoming);
  if (list.length === 0) {
    return;
  }

  const skippedExtensions: string[] = [];
  const skippedSize: string[] = [];
  const skippedDuplicates: string[] = [];
  let skippedOverflow = 0;

  const existingKeys = new Set(items.value.map((item) => dedupeKey(item.file)));

  for (const file of list) {
    if (!isAllowedFile(file)) {
      skippedExtensions.push(file.name);
      continue;
    }
    if (file.size > MAX_FILE_SIZE_BYTES) {
      skippedSize.push(file.name);
      continue;
    }

    const key = dedupeKey(file);
    if (existingKeys.has(key)) {
      skippedDuplicates.push(file.name);
      continue;
    }
    if (items.value.length >= MAX_FILE_COUNT) {
      skippedOverflow += 1;
      continue;
    }

    items.value.push({
      id: makeItemId(),
      file,
      status: 'pending',
      progress: 0,
      error: null,
    });
    existingKeys.add(key);
  }

  const hints: string[] = [];
  if (skippedExtensions.length > 0) {
    hints.push(
      t('以下文件无法上传：{names}。仅支持 {types}。')
        .replace('{names}', skippedExtensions.join('、'))
        .replace('{types}', allowedFileTypesLabel.value),
    );
  }
  if (skippedSize.length > 0) {
    hints.push(
      t('以下文件超过 {size}，无法上传：{names}。')
        .replace('{size}', MAX_FILE_SIZE_LABEL)
        .replace('{names}', skippedSize.join('、')),
    );
  }
  if (skippedDuplicates.length > 0) {
    hints.push(
      t('重复文件未再次上传：{names}。').replace(
        '{names}',
        skippedDuplicates.join('、'),
      ),
    );
  }
  if (skippedOverflow > 0) {
    hints.push(
      t('一次最多上传 {count} 个文件，其余文件未上传。').replace(
        '{count}',
        String(MAX_FILE_COUNT),
      ),
    );
  }
  localHint.value = hints.length > 0 ? hints.join(' ') : null;

  void processQueue();
}

function onFileInputChange(event: Event): void {
  const target = event.target as HTMLInputElement;
  appendFiles(target.files);
  target.value = '';
}

function openFilePicker(): void {
  if (dropzoneDisabled.value) {
    return;
  }
  fileInputRef.value?.click();
}

function onDragEnter(event: DragEvent): void {
  event.preventDefault();
  if (dropzoneDisabled.value) {
    return;
  }
  isDraggingOver.value = true;
}

function onDragOver(event: DragEvent): void {
  event.preventDefault();
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'copy';
  }
}

function onDragLeave(event: DragEvent): void {
  event.preventDefault();
  if (event.currentTarget === event.target) {
    isDraggingOver.value = false;
  }
}

function onDrop(event: DragEvent): void {
  event.preventDefault();
  isDraggingOver.value = false;
  if (dropzoneDisabled.value) {
    return;
  }
  appendFiles(event.dataTransfer?.files ?? null);
}

function removeItem(id: string): void {
  const target = items.value.find((item) => item.id === id);
  if (target?.status === 'uploading') {
    return;
  }
  items.value = items.value.filter((item) => item.id !== id);
}

function extractError(error: unknown): string {
  if (axios.isAxiosError(error)) {
    const axiosError = error as AxiosError<{
      message?: string;
      errors?: Record<string, string[]>;
    }>;
    const data = axiosError.response?.data;
    if (data?.errors) {
      const firstField = Object.keys(data.errors)[0];
      const firstMessage = firstField ? data.errors[firstField]?.[0] : null;
      if (firstMessage) {
        return firstMessage;
      }
    }
    if (axiosError.code === 'ERR_NETWORK') {
      return t('网络异常，请检查网络后重试。');
    }
    return t('上传失败，请稍后重试。');
  }
  return t('上传失败，请稍后重试。');
}

async function uploadOne(item: UploadItem): Promise<boolean> {
  item.status = 'uploading';
  item.progress = 0;
  item.error = null;

  const formData = new FormData();
  formData.append('files[]', item.file);
  if (props.groupId) {
    formData.append('group_id', props.groupId);
  }

  try {
    await axios.post(
      KnowledgeBase.Document.UploadKnowledgeDocumentAction.url({
        knowledgeBase: props.knowledgeBaseId,
      }),
      formData,
      {
        headers: {
          Accept: 'application/json',
          'Content-Type': 'multipart/form-data',
        },
        onUploadProgress: (event) => {
          if (!event.total) {
            return;
          }
          item.progress = Math.round((event.loaded / event.total) * 100);
        },
      },
    );

    item.status = 'success';
    item.progress = 100;
    return true;
  } catch (error) {
    item.status = 'failed';
    item.error = extractError(error);
    return false;
  }
}

async function processQueue(): Promise<void> {
  if (isProcessing.value) {
    return;
  }
  if (!items.value.some((item) => item.status === 'pending')) {
    return;
  }

  isProcessing.value = true;
  emit('update:processing', true);
  let hasUploadedFile = false;
  try {
    while (true) {
      const next = items.value.find((item) => item.status === 'pending');
      if (!next) {
        break;
      }
      const ok = await uploadOne(next);
      if (ok) {
        hasUploadedFile = true;
      }
    }
  } finally {
    isProcessing.value = false;
    emit('update:processing', false);
    if (hasUploadedFile) {
      emit('completed');
    }
  }
}

function retryFailed(): void {
  for (const item of items.value) {
    if (item.status === 'failed') {
      item.status = 'pending';
      item.error = null;
      item.progress = 0;
    }
  }
  void processQueue();
}

function close(): void {
  if (isProcessing.value) {
    return;
  }
  emit('cancel');
}

const dropzoneDisabled = computed(() => items.value.length >= MAX_FILE_COUNT);
</script>

<template>
  <div class="mx-auto w-full max-w-none space-y-6">
    <HeadingSmall
      v-if="props.showHeading !== false"
      :title="t('上传文档')"
      :description="
        t('支持 {types}；单个文件不超过 {size}，一次最多上传 {count} 个。')
          .replace('{types}', allowedFileTypesLabel)
          .replace('{size}', MAX_FILE_SIZE_LABEL)
          .replace('{count}', String(MAX_FILE_COUNT))
      "
    />

    <div class="space-y-6">
      <button
        type="button"
        class="flex w-full flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-8 text-center transition-colors"
        :class="[
          isDraggingOver
            ? 'border-foreground bg-muted/50'
            : 'border-border hover:bg-muted/30',
          dropzoneDisabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
        ]"
        :disabled="dropzoneDisabled"
        @click="openFilePicker"
        @dragenter="onDragEnter"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
      >
        <UploadCloud class="h-8 w-8 text-muted-foreground" />
        <p class="text-sm font-medium">
          {{ t('选择文件，或拖到这里') }}
        </p>
        <input
          ref="fileInputRef"
          type="file"
          class="hidden"
          multiple
          :accept="ACCEPT_ATTRIBUTE"
          @change="onFileInputChange"
        />
      </button>

      <p v-if="localHint" class="text-xs text-muted-foreground">
        {{ localHint }}
      </p>

      <div v-if="items.length > 0" class="space-y-2">
        <div
          class="flex items-center justify-between text-xs text-muted-foreground"
        >
          <span>
            {{
              t('共 {count} 个文件').replace('{count}', String(items.length))
            }}
          </span>
          <span>{{ formatFileSize(totalSize) }}</span>
        </div>

        <ul class="max-h-80 space-y-1.5 overflow-y-auto rounded-md border p-2">
          <li
            v-for="item in items"
            :key="item.id"
            class="rounded-md px-2 py-1.5 text-sm hover:bg-muted/40"
          >
            <div class="flex items-center gap-2">
              <FileText
                v-if="item.status === 'pending'"
                class="h-4 w-4 shrink-0 text-muted-foreground"
              />
              <LoaderCircle
                v-else-if="item.status === 'uploading'"
                class="h-4 w-4 shrink-0 animate-spin text-muted-foreground"
              />
              <Check
                v-else-if="item.status === 'success'"
                class="h-4 w-4 shrink-0 text-foreground"
              />
              <CircleAlert v-else class="h-4 w-4 shrink-0 text-destructive" />

              <span class="min-w-0 flex-1 truncate">
                {{ item.file.name }}
              </span>

              <span class="shrink-0 text-xs text-muted-foreground">
                <template v-if="item.status === 'uploading'">
                  {{ item.progress }}%
                </template>
                <template v-else>
                  {{ formatFileSize(item.file.size) }}
                </template>
              </span>

              <Button
                v-if="item.status !== 'uploading' && item.status !== 'success'"
                type="button"
                variant="ghost"
                size="icon"
                class="h-6 w-6 shrink-0 text-muted-foreground hover:text-destructive"
                :aria-label="t('移除')"
                @click="removeItem(item.id)"
              >
                <X class="h-3.5 w-3.5" />
              </Button>
            </div>

            <div
              v-if="item.status === 'uploading'"
              role="progressbar"
              :aria-label="
                t('正在上传 {name}', {
                  name: item.file.name,
                })
              "
              aria-valuemin="0"
              aria-valuemax="100"
              :aria-valuenow="item.progress"
              class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-muted"
            >
              <div
                class="h-full bg-foreground transition-[width]"
                :style="{ width: `${item.progress}%` }"
              ></div>
            </div>

            <p
              v-else-if="item.status === 'failed' && item.error"
              class="mt-1 ml-6 text-xs text-destructive"
            >
              {{ item.error }}
            </p>
          </li>
        </ul>
      </div>

      <div class="flex items-center justify-end gap-3">
        <p
          v-if="isProcessing"
          role="status"
          aria-live="polite"
          class="mr-auto flex items-center gap-1.5 text-xs text-muted-foreground"
        >
          <LoaderCircle class="h-3.5 w-3.5 animate-spin" />
          {{ t('正在上传…') }}
        </p>
        <Button
          type="button"
          variant="secondary"
          :disabled="isProcessing"
          @click="close"
        >
          {{ t('关闭') }}
        </Button>
        <Button
          v-if="hasFailedItems"
          type="button"
          :disabled="isProcessing"
          @click="retryFailed"
        >
          <RotateCw class="mr-1.5 h-4 w-4" />
          {{ t('重新上传失败文件') }}
        </Button>
      </div>
    </div>
  </div>
</template>
