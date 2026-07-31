<!--
  文件说明：应用设置「新增 AI 模型」页，消费 ShowCreateAiModelPagePropsData，承接供应商/类型/用途选择与录入。
-->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type { ShowCreateAiModelPagePropsData } from '@/types/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ModelForm from './ModelForm.vue';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const props = defineProps<ShowCreateAiModelPagePropsData>();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('AI 模型'), href: app.manage.aiModels.index.url() },
  { title: t('新增模型') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('新增模型')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto w-full max-w-none space-y-12">
        <div class="space-y-6">
          <PageBreadcrumb :items="breadcrumbItems" />

          <HeadingSmall
            :title="t('新增模型')"
            :description="t('选择供应商与用途，录入参与当前应用调度的模型')"
          />

          <ModelForm
            mode="create"
            :provider-options="props.provider_options"
            :purpose-options="props.purpose_options"
            :default-models-by-brand="props.default_models_by_brand"
          />
        </div>
      </div>
    </div>
  </div>
</template>
