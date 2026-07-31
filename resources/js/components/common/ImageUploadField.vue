<!--
  通用图片上传字段，展示预览和上传进度，并同步表单中的附件值。
-->
<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
  type AttachmentPurpose,
  type UploadedAttachmentData,
  resolveAttachmentUploadError,
  useAttachmentUploader,
} from '@/composables/useAttachmentUploader';
import { useI18n } from '@/composables/useI18n';
import { computed, nextTick, ref, watch } from 'vue';

type ImageUploadFieldVariant = 'avatar' | 'logo';

const props = withDefaults(
  defineProps<{
    label: string;
    name: string;
    responseKey?: keyof UploadedAttachmentData;
    purpose: AttachmentPurpose;
    uploadContext?: Record<string, unknown>;
    initialPreview?: string;
    initialValue?: string;
    accept?: string;
    disabled?: boolean;
    helpText?: string;
    buttonText?: string;
    error?: string;
    variant?: ImageUploadFieldVariant;
    inputId?: string;
  }>(),
  {
    accept: 'image/*',
    disabled: false,
    helpText: undefined,
    buttonText: undefined,
    error: undefined,
    variant: 'avatar',
    responseKey: 'id',
    uploadContext: () => ({}),
    initialPreview: '',
    initialValue: '',
    inputId: undefined,
  },
);

const emit = defineEmits<{
  'update:preview': [url: string];
  'update:value': [value: string];
  'update:uploading': [uploading: boolean];
}>();

const { t } = useI18n();
const { upload } = useAttachmentUploader();

const effectiveButtonText = computed(() => props.buttonText ?? t('选择图片'));
const effectiveHelpText = computed(() => props.helpText ?? '');

const uploading = ref(false);
const progress = ref(0);
const localError = ref<string>('');
const selectedFileName = ref<string>('');
const previewUrl = ref<string>(props.initialPreview || '');
const value = ref<string>(props.initialValue || '');
const hiddenInputRef = ref<HTMLInputElement | null>(null);

watch(previewUrl, (next) => emit('update:preview', next));
watch(uploading, (next) => emit('update:uploading', next));

watch(
  () => props.initialPreview,
  (next) => {
    if (uploading.value) return;
    if (selectedFileName.value) return;
    previewUrl.value = next || '';
  },
);

watch(
  () => props.initialValue,
  (next) => {
    if (uploading.value) return;
    if (selectedFileName.value) return;
    value.value = next || '';
  },
);

const fileInputId = computed(() => props.inputId || `${props.name}File`);
const hiddenInputValue = computed(
  () => value.value || props.initialValue || '',
);

const previewWrapperClass = computed(() => {
  if (props.variant === 'logo') {
    return 'relative flex h-32 w-32 items-center justify-center overflow-hidden rounded-md border bg-gray-50';
  }
  return 'relative flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border bg-gray-50';
});

const previewImageClass = computed(() => {
  if (props.variant === 'logo') {
    return 'max-h-full max-w-full object-contain';
  }
  return 'h-full w-full object-cover';
});

const handleFileChange = async (event: Event) => {
  if (props.disabled) return;
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];

  if (!file) return;

  selectedFileName.value = file.name;

  try {
    uploading.value = true;
    progress.value = 0;
    localError.value = '';

    const attachment = await upload(file, {
      purpose: props.purpose,
      context: props.uploadContext,
      onProgress: (value) => {
        progress.value = value;
      },
    });

    const uploadedValue = attachment[props.responseKey];
    value.value = typeof uploadedValue === 'string' ? uploadedValue : '';
    previewUrl.value = attachment.full_url || previewUrl.value;
    emit('update:value', value.value);
    await nextTick();
    hiddenInputRef.value?.dispatchEvent(new Event('input', { bubbles: true }));
  } catch (error) {
    localError.value = resolveAttachmentUploadError(error, t);
  } finally {
    uploading.value = false;
    progress.value = 0;
    target.value = '';
  }
};
</script>

<template>
  <div class="grid gap-2">
    <Label :for="fileInputId">{{ label }}</Label>
    <div class="mt-1 space-y-3">
      <div v-if="previewUrl" :class="previewWrapperClass">
        <img
          :src="previewUrl"
          :alt="t('图片预览')"
          :class="previewImageClass"
        />
        <div
          v-if="uploading"
          class="absolute inset-0 flex items-center justify-center bg-black/50"
        >
          <span class="text-sm text-white">{{ t('上传中...') }}</span>
          <span class="ml-2 text-sm text-white">{{ progress }}%</span>
        </div>
      </div>

      <input
        :id="name"
        ref="hiddenInputRef"
        :name="name"
        type="hidden"
        :value="hiddenInputValue"
      />

      <div class="flex items-center gap-3">
        <input
          :id="fileInputId"
          type="file"
          :accept="accept"
          class="sr-only"
          :disabled="uploading || disabled"
          @change="handleFileChange"
        />
        <Button as-child variant="outline" :disabled="uploading || disabled">
          <Label :for="fileInputId" class="cursor-pointer">
            {{ effectiveButtonText }}
          </Label>
        </Button>
        <span class="text-sm text-muted-foreground">
          {{ selectedFileName || t('未选择任何文件') }}
        </span>
      </div>

      <p v-if="effectiveHelpText" class="text-sm text-muted-foreground">
        {{ effectiveHelpText }}
      </p>
    </div>
    <InputError class="mt-2" :message="error || localError" />
  </div>
</template>
