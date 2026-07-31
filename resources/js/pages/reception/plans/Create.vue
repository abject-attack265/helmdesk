<!-- 添加接待方案页，使用 CreateReceptionPlanPagePropsData 填写方案基本信息。 -->
<script setup lang="ts">
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { useI18n } from '@/composables/useI18n';
import { appContentLayout } from '@/layouts/pageLayouts';
import CreatePlanPanel from '@/pages/reception/plans/CreatePlanDialog.vue';
import app from '@/routes/app';
import type { CreateReceptionPlanPagePropsData } from '@/types/generated';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: appContentLayout });

const props = defineProps<CreateReceptionPlanPagePropsData>();
const { t } = useI18n();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('接待方案'),
    href: app.manage.reception.plans.index.url(),
  },
  { title: t('添加接待方案') },
]);

function goToList(): void {
  router.visit(app.manage.reception.plans.index.url());
}
</script>

<template>
  <div class="contents">
    <Head :title="t('添加接待方案')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto w-full max-w-none space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <CreatePlanPanel
          :persona-tone-options="props.persona_tone_options"
          @cancel="goToList"
        />
      </div>
    </div>
  </div>
</template>
