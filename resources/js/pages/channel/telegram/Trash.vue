<!--
  Telegram 渠道回收站，使用 ShowTelegramChannelTrashPagePropsData 展示并恢复已删除渠道。
-->
<script setup lang="ts">
import Telegram from '@/actions/App/Actions/Channel/Telegram';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import RestoreConfirmDialog from '@/components/common/RestoreConfirmDialog.vue';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ShowTelegramChannelTrashPagePropsData } from '@/types/generated';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowTelegramChannelTrashPagePropsData>();
const { t } = useI18n();
const { formatDateTime } = useDateTime();
const restoreForm = useForm({});

const buildTrashPageUrl = (page: number): string => {
  return Telegram.ListTelegramChannelTrashAction.url({ query: { page } });
};

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('接入渠道') },
  {
    title: t('Telegram'),
    href: Telegram.ListTelegramChannelsAction.url(),
  },
  { title: t('回收站') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('Telegram 渠道回收站')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <HeadingSmall
          :title="t('Telegram 渠道回收站')"
          :description="t('已删除的 Telegram 渠道可以在这里恢复。')"
        />

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('渠道名称') }}</th>
                  <th class="px-4 py-3">{{ t('接待方案') }}</th>
                  <th class="px-4 py-3">{{ t('删除时间') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="channel in props.trashed_channel_list"
                  :key="channel.id"
                  class="border-b last:border-b-0"
                >
                  <td class="px-4 py-3">
                    <div class="font-medium">{{ channel.name }}</div>
                    <div class="text-xs text-muted-foreground">
                      <span v-if="channel.bot_username">
                        @{{ channel.bot_username }}
                      </span>
                      <span v-else>{{ t('未连接机器人') }}</span>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    <span v-if="channel.reception_plan_name">
                      {{ channel.reception_plan_name }}
                    </span>
                    <span v-else>{{ t('未选择接待方案') }}</span>
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{
                      channel.deleted_at
                        ? formatDateTime(channel.deleted_at)
                        : '-'
                    }}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <RestoreConfirmDialog
                      :title="t('恢复这个 Telegram 渠道？')"
                      :description="
                        t('恢复后会重新出现在 Telegram 渠道列表中。')
                      "
                      :processing="restoreForm.processing"
                      :submitting="restoreForm.processing"
                      @confirm="
                        restoreForm.put(
                          Telegram.RestoreTelegramChannelAction.url({
                            channel: channel.id,
                          }),
                          { preserveScroll: true },
                        )
                      "
                    >
                      <div class="font-medium">{{ channel.name }}</div>
                    </RestoreConfirmDialog>
                  </td>
                </tr>

                <tr v-if="props.trashed_channel_list.length === 0">
                  <td
                    colspan="4"
                    class="px-4 py-8 text-center text-muted-foreground"
                  >
                    {{ t('回收站里没有 Telegram 渠道') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="props.trashed_channel_list_pagination.last_page > 1"
            class="border-t p-4"
          >
            <PaginationNavigator
              :pagination="props.trashed_channel_list_pagination"
              :page-url="buildTrashPageUrl"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
