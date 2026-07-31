<!--
  知识库创建与编辑表单，供管理员填写名称、用途说明和图标。
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
import type { KnowledgeBaseData } from '@/types/generated';
import type { FormComponentRef } from '@inertiajs/core';
import type { RouteFormDefinition } from '@/wayfinder';
import { Form, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

type KnowledgeBaseFormDefinition =
  RouteFormDefinition<'post'> | RouteFormDefinition<'put'>;

withDefaults(
  defineProps<{
    formDefinition: KnowledgeBaseFormDefinition;
    title: string;
    description: string;
    submitLabel: string;
    knowledgeBaseForm?: KnowledgeBaseData | null;
  }>(),
  {
    knowledgeBaseForm: null,
  },
);

const { t } = useI18n();
const formRef = ref<FormComponentRef | null>(null);
const imageUploading = ref(false);
let allowNextGetNavigation = false;
let removeBeforeListener: (() => void) | null = null;

const listHref = computed(() => KnowledgeBase.ListKnowledgeBasesAction.url());

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
  if (!confirmDiscardIfDirty()) {
    return;
  }

  allowNextGetNavigation = true;
  router.visit(listHref.value);
}

function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (!hasUnsavedChanges()) {
    return;
  }

  event.preventDefault();
  event.returnValue = '';
}

onMounted(() => {
  removeBeforeListener = router.on('before', (event) => {
    if (event.detail.visit.method !== 'get') {
      return;
    }
    if (allowNextGetNavigation) {
      allowNextGetNavigation = false;
      return;
    }
    if (!confirmDiscardIfDirty()) {
      event.preventDefault();
    }
  });
  window.addEventListener('beforeunload', onBeforeUnload);
});

onBeforeUnmount(() => {
  removeBeforeListener?.();
  window.removeEventListener('beforeunload', onBeforeUnload);
});
</script>

<template>
  <div class="space-y-6">
    <HeadingSmall :title="title" :description="description" />

    <Form
      ref="formRef"
      v-bind="formDefinition"
      disable-while-processing
      class="space-y-6"
      v-slot="{ errors, processing }"
    >
      <input
        type="hidden"
        name="category"
        :value="knowledgeBaseForm?.category ?? 'standard'"
      />

      <FormField
        :label="t('知识库名称')"
        label-for="name"
        :error="errors.name"
        required
      >
        <Input
          id="name"
          name="name"
          class="mt-1 block w-full"
          :default-value="knowledgeBaseForm?.name"
          maxlength="120"
          :disabled="processing"
          required
        />
      </FormField>

      <FormField
        :label="t('用途说明（选填）')"
        label-for="description"
        :error="errors.description"
      >
        <Textarea
          id="description"
          name="description"
          rows="5"
          maxlength="1000"
          :disabled="processing"
          :default-value="knowledgeBaseForm?.description ?? ''"
          class="mt-1 min-h-32"
        />
      </FormField>

      <ImageUploadField
        :label="t('知识库图标')"
        name="avatar_id"
        purpose="avatar"
        :upload-context="{}"
        :initial-preview="
          knowledgeBaseForm?.avatar_url ?? defaultKnowledgeBaseAvatar
        "
        :initial-value="knowledgeBaseForm?.avatar_id ?? ''"
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
