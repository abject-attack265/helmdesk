<!--
  联系人回收站，使用 ShowContactTrashPagePropsData 展示并恢复已删除联系人。
-->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import RestoreConfirmDialog from '@/components/common/RestoreConfirmDialog.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import AppLayout from '@/layouts/AppLayout.vue';
import { getAvatarInitial } from '@/lib/initials';
import app from '@/routes/app';
import type {
  ShowContactTrashPagePropsData,
  TrashContactItemData,
} from '@/types/generated';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const { formatDateTime } = useDateTime();
const { formatVisitorName } = useVisitorDisplay();
const props = defineProps<ShowContactTrashPagePropsData>();

const restoreForm = useForm({});
const restoringContactId = ref<string | null>(null);

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('联系人'),
    href: app.contacts.index.url({
      type: 'all',
    }),
  },
  { title: t('回收站') },
]);

const buildContactTrashPageUrl = (page: number): string => {
  return app.contacts.trash.url({
    query: { page },
  });
};

const displayName = (contactItem: TrashContactItemData): string => {
  return formatVisitorName(contactItem.name, contactItem.id);
};

const displayIdentity = (contactItem: TrashContactItemData): string => {
  return contactItem.primary_email || contactItem.primary_phone || '-';
};

const nameInitial = (contactItem: TrashContactItemData): string =>
  getAvatarInitial(contactItem.name);

const typeBadgeVariant = (type: string): 'default' | 'secondary' =>
  type === 'contact' ? 'default' : 'secondary';

const restoreErrorMessage = (): string | undefined => {
  const errors = restoreForm.errors as Record<string, string | undefined>;

  return errors.contact;
};

const submitRestore = (contactItem: TrashContactItemData) => {
  restoringContactId.value = contactItem.id;
  restoreForm.clearErrors();

  restoreForm.put(
    app.contacts.restore.url({
      id: contactItem.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        restoreForm.clearErrors();
      },
      onFinish: () => {
        restoringContactId.value = null;
      },
    },
  );
};
</script>

<template>
  <div class="contents">
    <Head :title="t('联系人回收站')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <HeadingSmall
          :title="t('联系人回收站')"
          :description="t('查看和恢复已移到回收站的联系人')"
        />

        <div class="min-w-0 rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('名称') }}</th>
                  <th class="px-4 py-3">{{ t('联系方式') }}</th>
                  <th class="px-4 py-3">{{ t('类型') }}</th>
                  <th class="px-4 py-3">{{ t('首次来源') }}</th>
                  <th class="px-4 py-3">{{ t('创建时间') }}</th>
                  <th class="px-4 py-3">{{ t('移入时间') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="contactItem in props.contact_trash_list"
                  :key="contactItem.id"
                  class="border-b last:border-b-0"
                >
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                      <Avatar class="h-8 w-8">
                        <AvatarImage :src="contactItem.avatar_url" />
                        <AvatarFallback class="text-xs">
                          {{ nameInitial(contactItem) }}
                        </AvatarFallback>
                      </Avatar>
                      <span class="font-medium">
                        {{ displayName(contactItem) }}
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{ displayIdentity(contactItem) }}
                  </td>
                  <td class="px-4 py-3">
                    <Badge
                      :variant="
                        typeBadgeVariant(String(contactItem.type.value))
                      "
                    >
                      {{ contactItem.type.label }}
                    </Badge>
                  </td>
                  <td class="px-4 py-3">
                    <Badge variant="secondary">
                      {{ contactItem.source.label }}
                    </Badge>
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{ formatDateTime(contactItem.created_at) }}
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{
                      contactItem.deleted_at
                        ? formatDateTime(contactItem.deleted_at)
                        : '-'
                    }}
                  </td>
                  <td
                    class="px-4 py-3 text-right whitespace-nowrap"
                    @click.stop
                  >
                    <RestoreConfirmDialog
                      :title="t('恢复此联系人？')"
                      :processing="restoreForm.processing"
                      :submitting="
                        restoreForm.processing &&
                        restoringContactId === contactItem.id
                      "
                      :error-message="restoreErrorMessage()"
                      @update:open="restoreForm.clearErrors()"
                      @confirm="submitRestore(contactItem)"
                    >
                      <div class="font-medium">
                        {{ displayName(contactItem) }}
                      </div>
                      <div class="text-muted-foreground">
                        {{ displayIdentity(contactItem) }}
                      </div>
                      <div class="text-muted-foreground">
                        {{ t('恢复后会重新出现在联系人列表中。') }}
                      </div>
                    </RestoreConfirmDialog>
                  </td>
                </tr>

                <tr v-if="props.contact_trash_list.length === 0">
                  <td
                    colspan="7"
                    class="px-4 py-8 text-center text-muted-foreground"
                  >
                    {{ t('回收站暂无联系人') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="props.contact_trash_list_pagination.last_page > 1"
            class="border-t p-4"
          >
            <PaginationNavigator
              :pagination="props.contact_trash_list_pagination"
              :page-url="buildContactTrashPageUrl"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
