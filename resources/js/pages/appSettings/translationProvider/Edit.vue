<!--
  文件说明：应用设置「编辑翻译供应商」页，消费 ShowEditTranslationProviderPagePropsData，承接名称、凭据和启用状态调整。
-->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type { ShowEditTranslationProviderPagePropsData } from '@/types/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import TranslationProviderForm from './TranslationProviderForm.vue';

defineOptions({ layout: AppLayout });
const { t } = useI18n();
const props = defineProps<ShowEditTranslationProviderPagePropsData>();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('翻译'), href: app.manage.translationProviders.index.url() },
  { title: props.provider.name },
  { title: t('编辑翻译供应商') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('编辑翻译供应商')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto w-full max-w-none space-y-12">
        <div class="space-y-6">
          <PageBreadcrumb :items="breadcrumbItems" />

          <HeadingSmall
            :title="t('编辑翻译供应商')"
            :description="t('调整翻译供应商的名称、凭据和启用状态。')"
          />

          <TranslationProviderForm mode="edit" :provider="props.provider" />
        </div>
      </div>
    </div>
  </div>
</template>
