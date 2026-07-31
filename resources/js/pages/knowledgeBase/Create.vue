<!--
  文件说明：创建知识库页面。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { useI18n } from '@/composables/useI18n';
import KnowledgeBasesLayout from '@/layouts/KnowledgeBasesLayout.vue';
import { appContentLayout } from '@/layouts/pageLayouts';
import KnowledgeBaseForm from '@/pages/knowledgeBase/KnowledgeBaseForm.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: appContentLayout });

const { t } = useI18n();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('知识库'),
    href: KnowledgeBase.ListKnowledgeBasesAction.url(),
  },
  { title: t('添加知识库') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('添加知识库')" />

    <KnowledgeBasesLayout>
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <KnowledgeBaseForm
          :form-definition="{
            action: KnowledgeBase.CreateKnowledgeBaseAction.url(),
            method: 'post',
          }"
          :title="t('添加知识库')"
          :description="t('添加后即可上传文档或填写问答。')"
          :submit-label="t('添加')"
        />
      </div>
    </KnowledgeBasesLayout>
  </div>
</template>
