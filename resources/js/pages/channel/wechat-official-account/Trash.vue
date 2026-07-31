<!--
  微信公众号渠道回收站，使用 ShowWechatOfficialAccountChannelTrashPagePropsData 展示并恢复已删除渠道。
-->
<script setup lang="ts">
import WechatOfficialAccount from '@/actions/App/Actions/Channel/WechatOfficialAccount';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import RestoreConfirmDialog from '@/components/common/RestoreConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ShowWechatOfficialAccountChannelTrashPagePropsData } from '@/types/generated';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowWechatOfficialAccountChannelTrashPagePropsData>();
const { t } = useI18n();
const { formatDateTime } = useDateTime();
const restoreForm = useForm({});

const buildTrashPageUrl = (page: number): string => {
  return WechatOfficialAccount.ListWechatOfficialAccountChannelTrashAction.url({
    query: { page },
  });
};

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('接入渠道') },
  {
    title: t('微信公众号'),
    href: WechatOfficialAccount.ListWechatOfficialAccountChannelsAction.url(),
  },
  { title: t('回收站') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('微信公众号渠道回收站')" />
    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />
        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            :title="t('微信公众号渠道回收站')"
            :description="t('已删除的微信公众号渠道可以在这里恢复。')"
          />
          <Button variant="outline" as-child>
            <Link
              :href="
                WechatOfficialAccount.ListWechatOfficialAccountChannelsAction.url()
              "
              >{{ t('返回列表') }}</Link
            >
          </Button>
        </div>
        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('渠道名称') }}</th>
                  <th class="px-4 py-3">{{ t('AppID') }}</th>
                  <th class="px-4 py-3">{{ t('删除时间') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="channel in props.trashed_channel_list"
                  :key="channel.id"
                  class="border-t"
                >
                  <td class="px-4 py-3 font-medium">{{ channel.name }}</td>
                  <td class="px-4 py-3 font-mono text-muted-foreground">
                    {{ channel.app_id || '—' }}
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{
                      channel.deleted_at
                        ? formatDateTime(channel.deleted_at)
                        : '—'
                    }}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <RestoreConfirmDialog
                      :title="t('恢复这个微信公众号渠道？')"
                      :description="
                        t('恢复后会重新显示在微信公众号渠道列表中。')
                      "
                      :processing="restoreForm.processing"
                      :submitting="restoreForm.processing"
                      @confirm="
                        restoreForm.put(
                          WechatOfficialAccount.RestoreWechatOfficialAccountChannelAction.url(
                            { channel: channel.id },
                          ),
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
                    {{ t('回收站里没有微信公众号渠道') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <PaginationNavigator
          v-if="props.trashed_channel_list_pagination.last_page > 1"
          :pagination="props.trashed_channel_list_pagination"
          :page-url="buildTrashPageUrl"
        />
      </div>
    </div>
  </div>
</template>
