<!--
  知识库内容创建与编辑面板，编辑时加载已有内容供管理员修改。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import FormActions from '@/components/common/FormActions.vue';
import FormField from '@/components/common/FormField.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import MarkdownEditor from '@/components/common/MarkdownEditor.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import type { ListKnowledgeDocumentItemData } from '@/types/generated';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';

type Mode = 'create' | 'edit';

const props = defineProps<{
  mode: Mode;
  knowledgeBaseId: string;
  groupOptions: Array<{ id: string; label: string }>;
  defaultGroupId: string | null;
  document: ListKnowledgeDocumentItemData | null;
}>();

const emit = defineEmits<{
  cancel: [];
  saved: [];
}>();

const { t } = useI18n();

const form = useForm<{
  title: string;
  content: string;
  group_id: string;
}>({
  title: '',
  content: '',
  group_id: '',
});

const loadingContent = ref(false);
const contentLoadError = ref<string | null>(null);
const initialFormSnapshot = ref<{
  title: string;
  content: string;
  group_id: string;
}>({
  title: '',
  content: '',
  group_id: '',
});

onMounted(initializeFormForCurrentTarget);

function snapshotInitial(): void {
  initialFormSnapshot.value = {
    title: form.title,
    content: form.content,
    group_id: form.group_id,
  };
}

function initializeFormForCurrentTarget(): void {
  form.clearErrors();
  contentLoadError.value = null;

  if (props.mode === 'create') {
    form.title = '';
    form.content = '';
    form.group_id = props.defaultGroupId ?? props.groupOptions[0]?.id ?? '';
    loadingContent.value = false;
    snapshotInitial();
    return;
  }

  const target = props.document;
  if (!target) {
    return;
  }

  form.title = target.original_filename;
  form.content = '';
  form.group_id = target.group_id;
  snapshotInitial();
  void loadContentForEdit(target);
}

async function loadContentForEdit(
  target: ListKnowledgeDocumentItemData,
): Promise<void> {
  contentLoadError.value = null;
  loadingContent.value = true;
  try {
    const url =
      KnowledgeBase.Document.StreamKnowledgeDocumentPreviewFileAction.url({
        knowledgeBase: props.knowledgeBaseId,
        document: target.id,
      });
    const response = await fetch(url);
    if (!response.ok) {
      throw new Error(`Failed to load document content: ${response.status}`);
    }
    form.content = await response.text();
    snapshotInitial();
  } catch {
    contentLoadError.value = t('内容加载失败，请重试。');
  } finally {
    loadingContent.value = false;
  }
}

const isDirty = computed(() => {
  if (loadingContent.value) {
    return false;
  }
  return (
    form.title !== initialFormSnapshot.value.title ||
    form.content !== initialFormSnapshot.value.content ||
    form.group_id !== initialFormSnapshot.value.group_id
  );
});

function hasUnsavedChanges(): boolean {
  return isDirty.value || form.processing;
}

function confirmDiscardIfDirty(): boolean {
  if (form.processing) {
    return false;
  }
  if (!isDirty.value) {
    return true;
  }
  return window.confirm(t('内容尚未保存，确定离开吗？未保存的修改会丢失。'));
}

defineExpose({ confirmDiscardIfDirty, hasUnsavedChanges });

const submitDisabled = computed(
  () =>
    form.processing ||
    loadingContent.value ||
    form.title.trim() === '' ||
    form.content.trim() === '',
);

function submit(): void {
  if (submitDisabled.value) {
    return;
  }

  if (props.mode === 'create') {
    form
      .transform((data) => ({
        title: data.title,
        content: data.content,
        group_id: data.group_id || null,
      }))
      .post(
        KnowledgeBase.Document.CreateManualKnowledgeDocumentAction.url({
          knowledgeBase: props.knowledgeBaseId,
        }),
        {
          preserveScroll: true,
          onSuccess: () => {
            emit('saved');
          },
        },
      );
    return;
  }

  const target = props.document;
  if (!target) {
    return;
  }

  form
    .transform((data) => ({
      title: data.title,
      content: data.content,
    }))
    .put(
      KnowledgeBase.Document.UpdateManualKnowledgeDocumentAction.url({
        knowledgeBase: props.knowledgeBaseId,
        document: target.id,
      }),
      {
        preserveScroll: true,
        onSuccess: () => {
          emit('saved');
        },
      },
    );
}

function close(): void {
  if (form.processing) {
    return;
  }
  if (!confirmDiscardIfDirty()) {
    return;
  }
  emit('cancel');
}
</script>

<template>
  <div class="mx-auto w-full max-w-none space-y-6">
    <HeadingSmall :title="mode === 'create' ? t('添加内容') : t('编辑内容')" />

    <form class="space-y-6" @submit.prevent="submit">
      <FormField
        :label="t('标题')"
        label-for="manual-document-title"
        :error="form.errors.title"
        required
      >
        <Input
          id="manual-document-title"
          v-model="form.title"
          class="mt-1 block w-full"
          type="text"
          autocomplete="off"
          maxlength="200"
          :aria-invalid="Boolean(form.errors.title)"
          :disabled="
            form.processing || loadingContent || contentLoadError !== null
          "
          required
        />
      </FormField>

      <FormField
        v-if="mode === 'create' && groupOptions.length > 0"
        :label="t('分组')"
        label-for="manual-document-group"
        :error="form.errors.group_id"
      >
        <Select v-model="form.group_id" :disabled="form.processing">
          <SelectTrigger id="manual-document-group" class="mt-1 w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="group in groupOptions"
              :key="group.id"
              :value="group.id"
            >
              {{ group.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </FormField>

      <FormField
        :label="t('内容')"
        label-for="manual-document-content"
        :error="form.errors.content"
        required
      >
        <div
          v-if="contentLoadError"
          class="mt-1 flex h-48 flex-col items-center justify-center gap-3 rounded-md border border-dashed text-center"
        >
          <p class="text-sm text-muted-foreground">
            {{ contentLoadError }}
          </p>
          <Button
            v-if="document"
            type="button"
            variant="outline"
            size="sm"
            @click="loadContentForEdit(document)"
          >
            {{ t('重新加载') }}
          </Button>
        </div>
        <div
          v-else-if="loadingContent"
          class="mt-1 flex h-[640px] items-center justify-center rounded-md border border-dashed"
        >
          <Spinner class="h-5 w-5" />
        </div>
        <MarkdownEditor
          v-else
          id="manual-document-content"
          v-model="form.content"
          role="group"
          aria-required="true"
          :height="640"
          :disabled="form.processing"
        />
      </FormField>

      <FormActions
        :submit-label="form.processing ? t('保存中...') : t('保存')"
        :processing="form.processing"
        :submit-disabled="submitDisabled"
      >
        <template #submit>
          <LoaderCircle
            v-if="form.processing"
            class="mr-1.5 h-4 w-4 animate-spin"
          />
          {{ form.processing ? t('保存中...') : t('保存') }}
        </template>
        <Button
          type="button"
          variant="outline"
          :disabled="form.processing"
          @click="close"
        >
          {{ t('取消') }}
        </Button>
      </FormActions>
    </form>
  </div>
</template>
