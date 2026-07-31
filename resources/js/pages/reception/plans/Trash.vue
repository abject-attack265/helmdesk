<!-- 接待方案回收站，使用 ListReceptionPlanTrashPagePropsData 展示并恢复已删除方案。 -->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import RestoreConfirmDialog from '@/components/common/RestoreConfirmDialog.vue';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { appContentLayout } from '@/layouts/pageLayouts';
import app from '@/routes/app';
import type { ListReceptionPlanTrashPagePropsData } from '@/types/generated';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: appContentLayout });

const props = defineProps<ListReceptionPlanTrashPagePropsData>();
const { t } = useI18n();
const { formatDateTime } = useDateTime();
const restoreForm = useForm({});

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('接待方案'),
    href: app.manage.reception.plans.index.url(),
  },
  { title: t('回收站') },
]);

const buildTrashPageUrl = (page: number): string =>
  app.manage.reception.plans.trash.url({
    query: { page },
  });
</script>

<template>
  <div class="contents">
    <Head :title="t('接待方案回收站')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <HeadingSmall
          :title="t('接待方案回收站')"
          :description="t('查看已删除的接待方案，可以随时恢复。')"
        />

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('方案名称') }}</th>
                  <th class="px-4 py-3">{{ t('删除时间') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="plan in props.trashed_plan_list"
                  :key="plan.id"
                  class="border-b last:border-b-0"
                >
                  <td class="px-4 py-3">
                    <div class="font-medium">{{ plan.name }}</div>
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{
                      plan.deleted_at ? formatDateTime(plan.deleted_at) : '—'
                    }}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <RestoreConfirmDialog
                      :title="t('恢复这个接待方案？')"
                      :description="t('恢复后会重新显示在接待方案列表中。')"
                      :processing="restoreForm.processing"
                      :submitting="restoreForm.processing"
                      @confirm="
                        restoreForm.put(
                          app.manage.reception.plans.restore.url({
                            plan: plan.id,
                          }),
                          { preserveScroll: true },
                        )
                      "
                    >
                      <div class="font-medium">{{ plan.name }}</div>
                    </RestoreConfirmDialog>
                  </td>
                </tr>

                <tr v-if="props.trashed_plan_list.length === 0">
                  <td
                    colspan="3"
                    class="px-4 py-8 text-center text-muted-foreground"
                  >
                    {{ t('回收站里没有接待方案') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="props.trashed_plan_list_pagination.last_page > 1"
            class="border-t p-4"
          >
            <PaginationNavigator
              :pagination="props.trashed_plan_list_pagination"
              :page-url="buildTrashPageUrl"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
