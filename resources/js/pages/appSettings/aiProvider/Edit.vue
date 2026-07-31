<!--
  文件说明：应用设置「编辑 AI 供应商」页，消费 ShowEditAiProviderPagePropsData，承接名称/凭据调整与连通测试。
-->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type { ShowEditAiProviderPagePropsData } from '@/types/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AiProviderForm from './AiProviderForm.vue';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const props = defineProps<ShowEditAiProviderPagePropsData>();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('AI 供应商'), href: app.manage.aiProviders.index.url() },
  { title: props.provider.name },
  { title: t('编辑 AI 供应商') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('编辑 AI 供应商')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto w-full max-w-none space-y-12">
        <div class="space-y-6">
          <PageBreadcrumb :items="breadcrumbItems" />

          <HeadingSmall
            :title="t('编辑 AI 供应商')"
            :description="t('调整 AI 供应商的名称与凭据。')"
          />

          <AiProviderForm mode="edit" :provider="props.provider" />
        </div>
      </div>
    </div>
  </div>
</template>
