<!--
  知识库编辑页，使用 ShowEditKnowledgeBasePagePropsData 修改名称、用途和图标。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { useI18n } from '@/composables/useI18n';
import KnowledgeBasesLayout from '@/layouts/KnowledgeBasesLayout.vue';
import { appContentLayout } from '@/layouts/pageLayouts';
import KnowledgeBaseForm from '@/pages/knowledgeBase/KnowledgeBaseForm.vue';
import type { ShowEditKnowledgeBasePagePropsData } from '@/types/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: appContentLayout });

const props = defineProps<ShowEditKnowledgeBasePagePropsData>();
const { t } = useI18n();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('知识库'),
    href: KnowledgeBase.ListKnowledgeBasesAction.url(),
  },
  { title: props.knowledge_base_form.name },
  { title: t('编辑知识库') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('编辑知识库')" />

    <KnowledgeBasesLayout>
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <KnowledgeBaseForm
          :form-definition="{
            action: KnowledgeBase.UpdateKnowledgeBaseAction.url({
              knowledgeBase: props.knowledge_base_form.id,
            }),
            method: 'put',
          }"
          :title="t('编辑知识库')"
          :description="t('修改知识库的名称、用途说明和图标。')"
          :submit-label="t('保存')"
          :knowledge-base-form="props.knowledge_base_form"
        />
      </div>
    </KnowledgeBasesLayout>
  </div>
</template>
