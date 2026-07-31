<!-- 添加集成页，使用 ShowCreateIntegrationPagePropsData 连接外部系统并测试连接。 -->
<script setup lang="ts">
import Integration from '@/actions/App/Actions/Integration';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ShowCreateIntegrationPagePropsData } from '@/types/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import IntegrationFormPanel from './IntegrationFormPanel.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowCreateIntegrationPagePropsData>();
const { t } = useI18n();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('设置') },
  {
    title: t('集成'),
    href: Integration.ShowInstanceIntegrationsAction.url(),
  },
  { title: t('添加集成') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('添加集成')" />

    <div class="px-4 py-6 sm:px-6">
      <PageBreadcrumb class="mb-6" :items="breadcrumbItems" />

      <IntegrationFormPanel
        mode="create"
        :provider-options="props.provider_options"
        :return-href="Integration.ShowInstanceIntegrationsAction.url()"
      />
    </div>
  </div>
</template>
