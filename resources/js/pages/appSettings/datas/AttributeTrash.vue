<!--
  自定义字段回收站，使用 ShowAttributeDefinitionTrashPagePropsData 展示并恢复已删除字段。
-->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import RestoreConfirmDialog from '@/components/common/RestoreConfirmDialog.vue';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type {
  ListAttributeDefinitionItemData,
  ShowAttributeDefinitionTrashPagePropsData,
} from '@/types/generated';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowAttributeDefinitionTrashPagePropsData>();
const { t } = useI18n();
const { formatDateTime } = useDateTime();
const restoreForm = useForm({});
const restoringDefinitionId = ref<string | null>(null);

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('自定义字段'),
    href: app.manage.attributes.index.url(),
  },
  { title: t('回收站') },
]);

const buildAttributeTrashPageUrl = (page: number): string => {
  return app.manage.attributes.trash.url({
    query: { page },
  });
};

const restoreErrorMessage = (): string | undefined => {
  const errors = restoreForm.errors as Record<string, string | undefined>;

  return errors.definition;
};

const submitRestore = (definition: ListAttributeDefinitionItemData) => {
  restoringDefinitionId.value = definition.id;
  restoreForm.clearErrors();

  restoreForm.put(
    app.manage.attributes.restore.url({
      id: definition.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        restoreForm.clearErrors();
      },
      onFinish: () => {
        restoringDefinitionId.value = null;
      },
    },
  );
};
</script>

<template>
  <div class="contents">
    <Head :title="t('自定义字段回收站')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <HeadingSmall
          :title="t('自定义字段回收站')"
          :description="t('查看和恢复已删除的自定义字段。')"
        />

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('字段名称') }}</th>
                  <th class="px-4 py-3">{{ t('内部标识') }}</th>
                  <th class="px-4 py-3">{{ t('填写方式') }}</th>
                  <th class="px-4 py-3">{{ t('已填写联系人') }}</th>
                  <th class="px-4 py-3">{{ t('删除时间') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="definition in props.trashed_definition_list"
                  :key="definition.id"
                  class="border-b last:border-b-0"
                >
                  <td class="px-4 py-3 font-medium">
                    {{ definition.name }}
                  </td>
                  <td class="px-4 py-3">
                    <code class="rounded bg-muted px-1.5 py-0.5 text-xs">
                      {{ definition.key }}
                    </code>
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{ definition.type_label }}
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{ definition.usage_count }}
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{
                      definition.deleted_at
                        ? formatDateTime(definition.deleted_at)
                        : '-'
                    }}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <RestoreConfirmDialog
                      :title="t('恢复这个字段？')"
                      :processing="restoreForm.processing"
                      :submitting="
                        restoreForm.processing &&
                        restoringDefinitionId === definition.id
                      "
                      :error-message="restoreErrorMessage()"
                      @update:open="restoreForm.clearErrors()"
                      @confirm="submitRestore(definition)"
                    >
                      <div class="font-medium">
                        {{ definition.name }}
                      </div>
                      <div class="mt-1 text-muted-foreground">
                        {{
                          t(
                            '恢复后会重新出现在自定义字段列表中，已有联系人数据可以继续使用。',
                          )
                        }}
                      </div>
                    </RestoreConfirmDialog>
                  </td>
                </tr>

                <tr v-if="props.trashed_definition_list.length === 0">
                  <td
                    colspan="6"
                    class="px-4 py-8 text-center text-muted-foreground"
                  >
                    {{ t('回收站中没有字段') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="props.trashed_definition_list_pagination.last_page > 1"
            class="border-t p-4"
          >
            <PaginationNavigator
              :pagination="props.trashed_definition_list_pagination"
              :page-url="buildAttributeTrashPageUrl"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
