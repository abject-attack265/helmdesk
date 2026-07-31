<!-- 接待方案列表页，使用 ShowReceptionPlanListPagePropsData 展示方案和管理入口。 -->
<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/composables/useI18n';
import { appContentLayout } from '@/layouts/pageLayouts';
import app from '@/routes/app';
import type { ShowReceptionPlanListPagePropsData } from '@/types/generated';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';

defineOptions({ layout: appContentLayout });

const props = defineProps<ShowReceptionPlanListPagePropsData>();
const { t } = useI18n();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('接待方案'),
    href: app.manage.reception.plans.index.url(),
  },
  { title: t('列表') },
]);

const deleteForm = useForm({});
const deletingPlanId = ref<string | null>(null);

const buildPlanListPageUrl = (page: number): string =>
  app.manage.reception.plans.index.url({
    query: { page },
  });

const selectedPlan = computed(
  () =>
    props.plan_list.find((plan) => plan.id === deletingPlanId.value) ?? null,
);

const confirmDelete = () => {
  if (!selectedPlan.value || deleteForm.processing) {
    return;
  }

  deleteForm.delete(
    app.manage.reception.plans.destroy.url({
      plan: selectedPlan.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        deletingPlanId.value = null;
      },
    },
  );
};

const handleDeleteDialogOpenChange = (open: boolean) => {
  if (!open) {
    deletingPlanId.value = null;
  }
};
</script>

<template>
  <div class="contents">
    <Head :title="t('接待方案')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            :title="t('接待方案')"
            :description="t('设置 AI 和客服如何接待访客，保存后生效。')"
          />

          <div class="flex items-center gap-2">
            <Button as-child>
              <Link :href="app.manage.reception.plans.create.url()">
                {{ t('添加接待方案') }}
              </Link>
            </Button>

            <Button variant="outline" as-child>
              <Link :href="app.manage.reception.plans.trash.url()">
                {{ t('回收站') }}
              </Link>
            </Button>
          </div>
        </div>

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('方案名称') }}</th>
                  <th class="px-4 py-3">{{ t('客服昵称') }}</th>
                  <th class="px-4 py-3">{{ t('回复语气') }}</th>
                  <th class="px-4 py-3">{{ t('知识库') }}</th>
                  <th class="px-4 py-3">{{ t('集成') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="plan in props.plan_list"
                  :key="plan.id"
                  class="border-t bg-background align-middle"
                >
                  <td class="px-4 py-3">
                    <span class="font-medium">{{ plan.name }}</span>
                  </td>

                  <td class="px-4 py-3 text-muted-foreground">
                    {{ plan.persona_config.display_name }}
                  </td>

                  <td class="px-4 py-3 text-muted-foreground">
                    {{ plan.persona_config.tone_label }}
                  </td>

                  <td class="px-4 py-3 text-muted-foreground">
                    {{ t('{count} 个', { count: plan.knowledge_bases_count }) }}
                  </td>

                  <td class="px-4 py-3 text-muted-foreground">
                    {{
                      t('{count} 个', { count: plan.integration_grants_count })
                    }}
                  </td>

                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-2">
                      <Button size="sm" variant="outline" as-child>
                        <Link
                          :href="
                            app.manage.reception.plans.show.url({
                              plan: plan.id,
                            })
                          "
                        >
                          {{ t('编辑') }}
                        </Link>
                      </Button>

                      <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                          <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :aria-label="t('更多操作')"
                          >
                            <MoreHorizontal class="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-36">
                          <DropdownMenuItem
                            class="text-destructive focus:text-destructive"
                            @select="deletingPlanId = plan.id"
                          >
                            {{ t('删除') }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </td>
                </tr>

                <tr v-if="props.plan_list.length === 0">
                  <td
                    colspan="6"
                    class="px-4 py-8 text-center text-muted-foreground"
                  >
                    {{ t('还没有接待方案') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="props.plan_list_pagination.last_page > 1"
            class="border-t p-4"
          >
            <PaginationNavigator
              :pagination="props.plan_list_pagination"
              :page-url="buildPlanListPageUrl"
            />
          </div>
        </div>

        <ConfirmDeleteDialog
          :open="deletingPlanId !== null"
          :title="t('删除这个接待方案？')"
          :detail-title="selectedPlan?.name"
          :detail-description="
            t('删除后会移到回收站，可以稍后恢复。正在使用的方案无法删除。')
          "
          :processing="deleteForm.processing"
          @update:open="handleDeleteDialogOpenChange"
          @confirm="confirmDelete"
        />
      </div>
    </div>
  </div>
</template>
