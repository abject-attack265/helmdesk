<!--
  知识库创建与编辑面板，供管理员填写名称、用途说明和图标。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import FormActions from '@/components/common/FormActions.vue';
import FormField from '@/components/common/FormField.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import ImageUploadField from '@/components/common/ImageUploadField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/useI18n';
import { defaultKnowledgeBaseAvatar } from '@/lib/knowledgeBaseAvatar';
import type {
  KnowledgeBaseCategory,
  KnowledgeBaseData,
} from '@/types/generated';
import type { FormComponentRef } from '@inertiajs/core';
import type { RouteFormDefinition } from '@/wayfinder';
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
  mode: 'create' | 'edit';
  category: KnowledgeBaseCategory;
  categoryLabel: string;
  knowledgeBase?: KnowledgeBaseData | null;
}>();

const emit = defineEmits<{
  cancel: [];
  saved: [];
}>();

const { t } = useI18n();
const formRef = ref<FormComponentRef | null>(null);
const imageUploading = ref(false);

const isEditMode = computed(() => props.mode === 'edit');

const title = computed(() =>
  isEditMode.value ? t('编辑知识库') : t('添加知识库'),
);

const submitLabel = computed(() => (isEditMode.value ? t('保存') : t('添加')));

const formDef = computed<RouteFormDefinition<'post'>>(() => {
  if (isEditMode.value && props.knowledgeBase) {
    return KnowledgeBase.UpdateKnowledgeBaseAction.form({
      knowledgeBase: props.knowledgeBase.id,
    });
  }

  return KnowledgeBase.CreateKnowledgeBaseAction.form({});
});

function onFormSuccess() {
  emit('saved');
}

function hasUnsavedChanges(): boolean {
  return Boolean(
    formRef.value?.isDirty || formRef.value?.processing || imageUploading.value,
  );
}

function confirmDiscardIfDirty(): boolean {
  if (formRef.value?.processing || imageUploading.value) {
    return false;
  }
  if (!formRef.value?.isDirty) {
    return true;
  }

  return window.confirm(t('内容尚未保存，确定离开吗？未保存的修改会丢失。'));
}

function cancel(): void {
  if (confirmDiscardIfDirty()) {
    emit('cancel');
  }
}

defineExpose({ confirmDiscardIfDirty, hasUnsavedChanges });
</script>

<template>
  <div class="mx-auto w-full max-w-none space-y-6">
    <HeadingSmall
      :title="title"
      :description="isEditMode ? '' : t('添加后即可上传文档或填写问答。')"
    />

    <Form
      ref="formRef"
      v-bind="formDef"
      :on-success="onFormSuccess"
      disable-while-processing
      class="space-y-6"
      v-slot="{ errors, processing }"
    >
      <FormField :label="t('知识库类型')">
        <div class="mt-1 rounded-md border px-3 py-2 text-sm">
          {{ props.categoryLabel }}
        </div>
        <input type="hidden" name="category" :value="props.category" />
      </FormField>

      <FormField
        :label="t('知识库名称')"
        label-for="kb-panel-name"
        :error="errors.name"
        required
      >
        <Input
          id="kb-panel-name"
          name="name"
          class="mt-1 block w-full"
          :default-value="isEditMode ? knowledgeBase?.name : undefined"
          maxlength="120"
          :disabled="processing"
          required
        />
      </FormField>

      <FormField
        :label="t('用途说明（选填）')"
        label-for="kb-panel-desc"
        :error="errors.description"
      >
        <Textarea
          id="kb-panel-desc"
          name="description"
          rows="3"
          maxlength="1000"
          :disabled="processing"
          :default-value="isEditMode ? (knowledgeBase?.description ?? '') : ''"
          class="mt-1 min-h-20"
        />
      </FormField>

      <ImageUploadField
        :label="t('知识库图标')"
        name="avatar_id"
        purpose="avatar"
        :upload-context="{}"
        :initial-preview="
          isEditMode
            ? (props.knowledgeBase?.avatar_url ?? defaultKnowledgeBaseAvatar)
            : defaultKnowledgeBaseAvatar
        "
        :initial-value="
          isEditMode ? (props.knowledgeBase?.avatar_id ?? '') : ''
        "
        variant="logo"
        :error="errors.avatar_id"
        :disabled="processing"
        @update:uploading="imageUploading = $event"
      />

      <FormActions
        :submit-label="submitLabel"
        :processing="processing"
        :submit-disabled="imageUploading"
      >
        <Button
          type="button"
          variant="outline"
          :disabled="processing"
          @click="cancel"
        >
          {{ t('取消') }}
        </Button>
      </FormActions>
    </Form>
  </div>
</template>
