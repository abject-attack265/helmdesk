<!--
  文件说明：应用设置「新增 AI 供应商」页，消费 ShowCreateAiProviderPagePropsData，承接品牌选择与凭据录入。
-->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type { ShowCreateAiProviderPagePropsData } from '@/types/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AiProviderForm from './AiProviderForm.vue';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const props = defineProps<ShowCreateAiProviderPagePropsData>();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('AI 供应商'), href: app.manage.aiProviders.index.url() },
  { title: t('新增 AI 供应商') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('新增 AI 供应商')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto w-full max-w-none space-y-12">
        <div class="space-y-6">
          <PageBreadcrumb :items="breadcrumbItems" />

          <HeadingSmall
            :title="t('新增 AI 供应商')"
            :description="t('选择品牌并填写凭据。')"
          />

          <AiProviderForm mode="create" :brand-options="props.brand_options" />
        </div>
      </div>
    </div>
  </div>
</template>
