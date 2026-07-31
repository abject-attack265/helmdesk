<!--
  标签回收站，使用 ShowTagTrashPagePropsData 展示并恢复已删除标签。
-->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import RestoreConfirmDialog from '@/components/common/RestoreConfirmDialog.vue';
import { Badge } from '@/components/ui/badge';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type {
  ListTagItemData,
  ShowTagTrashPagePropsData,
} from '@/types/generated';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowTagTrashPagePropsData>();
const { t } = useI18n();
const { formatDateTime } = useDateTime();
const restoreForm = useForm({});
const restoringTagId = ref<string | null>(null);

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('标签'),
    href: app.manage.tags.index.url(),
  },
  { title: t('回收站') },
]);

const buildTagTrashPageUrl = (page: number): string => {
  return app.manage.tags.trash.url({
    query: { page },
  });
};

const restoreErrorMessage = (): string | undefined => {
  const errors = restoreForm.errors as Record<string, string | undefined>;

  return errors.tag;
};

const tagUsageLabel = (tag: ListTagItemData): string => {
  if (tag.scope === 'conversation') {
    return t('用于 {count} 个会话', {
      count: tag.conversation_usage_count,
    });
  }

  if (tag.scope === 'contact') {
    return t('用于 {count} 个联系人', {
      count: tag.contact_usage_count,
    });
  }

  throw new Error(`未知的标签用途：${tag.scope}`);
};

const submitRestore = (tag: ListTagItemData) => {
  restoringTagId.value = tag.id;
  restoreForm.clearErrors();

  restoreForm.put(
    app.manage.tags.restore.url({
      id: tag.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        restoreForm.clearErrors();
      },
      onFinish: () => {
        restoringTagId.value = null;
      },
    },
  );
};
</script>

<template>
  <div class="contents">
    <Head :title="t('标签回收站')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <HeadingSmall
          :title="t('标签回收站')"
          :description="t('查看并恢复已删除的标签。')"
        />

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('名称') }}</th>
                  <th class="px-4 py-3">{{ t('标签用于') }}</th>
                  <th class="px-4 py-3">{{ t('颜色') }}</th>

                  <th class="px-4 py-3">{{ t('创建方式') }}</th>
                  <th class="px-4 py-3">{{ t('已标记') }}</th>
                  <th class="px-4 py-3">{{ t('删除时间') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="tag in props.trashed_tag_list"
                  :key="tag.id"
                  class="border-b last:border-b-0"
                >
                  <td class="px-4 py-3 font-medium">
                    {{ tag.name }}
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    <span v-if="tag.scope_label">
                      {{ tag.scope_label }}
                      <span v-if="tag.tag_group_name" class="text-xs">
                        · {{ tag.tag_group_name }}
                      </span>
                    </span>
                    <span v-else>-</span>
                  </td>
                  <td class="px-4 py-3">
                    <Badge
                      class="flex w-fit items-center gap-1.5 border bg-background text-foreground shadow-sm"
                    >
                      <span
                        class="h-2 w-2 shrink-0 rounded-full"
                        :style="{ backgroundColor: tag.color ?? '#94a3b8' }"
                      />
                      {{ tag.color ?? '-' }}
                    </Badge>
                  </td>

                  <td class="px-4 py-3 text-muted-foreground">
                    {{ tag.source_label }}
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{ tagUsageLabel(tag) }}
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{ tag.deleted_at ? formatDateTime(tag.deleted_at) : '-' }}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <RestoreConfirmDialog
                      :title="t('恢复这个标签？')"
                      :processing="restoreForm.processing"
                      :submitting="
                        restoreForm.processing && restoringTagId === tag.id
                      "
                      :error-message="restoreErrorMessage()"
                      @update:open="restoreForm.clearErrors()"
                      @confirm="submitRestore(tag)"
                    >
                      <div class="font-medium">{{ tag.name }}</div>
                      <div class="text-muted-foreground">
                        {{ t('恢复后会重新出现在原来的标签组中。') }}
                      </div>
                    </RestoreConfirmDialog>
                  </td>
                </tr>

                <tr v-if="props.trashed_tag_list.length === 0">
                  <td
                    colspan="7"
                    class="px-4 py-8 text-center text-muted-foreground"
                  >
                    {{ t('回收站中没有标签') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="props.trashed_tag_list_pagination.last_page > 1"
            class="border-t p-4"
          >
            <PaginationNavigator
              :pagination="props.trashed_tag_list_pagination"
              :page-url="buildTagTrashPageUrl"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
