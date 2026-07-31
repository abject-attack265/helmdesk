<!-- 集成编辑页，使用 ShowEditIntegrationPagePropsData 修改连接信息并测试连接。 -->
<script setup lang="ts">
import Integration from '@/actions/App/Actions/Integration';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ShowEditIntegrationPagePropsData } from '@/types/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import IntegrationFormPanel from './IntegrationFormPanel.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowEditIntegrationPagePropsData>();
const { t } = useI18n();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('设置') },
  {
    title: t('集成'),
    href: Integration.ShowInstanceIntegrationsAction.url(),
  },
  { title: props.server.name },
  { title: t('编辑集成') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('编辑集成')" />

    <div class="px-4 py-6 sm:px-6">
      <PageBreadcrumb class="mb-6" :items="breadcrumbItems" />

      <IntegrationFormPanel
        mode="edit"
        :server="props.server"
        :return-href="Integration.ShowInstanceIntegrationsAction.url()"
      />
    </div>
  </div>
</template>
