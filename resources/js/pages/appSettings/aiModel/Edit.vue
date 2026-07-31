<!--
  文件说明：应用设置「编辑 AI 模型」页，消费 ShowEditAiModelPagePropsData，承接名称/用途/启用调整。
-->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type { ShowEditAiModelPagePropsData } from '@/types/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ModelForm from './ModelForm.vue';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const props = defineProps<ShowEditAiModelPagePropsData>();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('AI 模型'), href: app.manage.aiModels.index.url() },
  { title: props.model.name },
  { title: t('编辑模型') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('编辑模型')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto w-full max-w-none space-y-12">
        <div class="space-y-6">
          <PageBreadcrumb :items="breadcrumbItems" />

          <HeadingSmall
            :title="t('编辑模型')"
            :description="t('调整模型的显示名称、用途、权重与启用状态')"
          />

          <ModelForm mode="edit" :model="props.model" />
        </div>
      </div>
    </div>
  </div>
</template>
