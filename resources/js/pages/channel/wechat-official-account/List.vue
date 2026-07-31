<!--
  微信公众号渠道列表页，使用 ShowWechatOfficialAccountChannelListPagePropsData 展示直连渠道和管理入口。
-->
<script setup lang="ts">
import WechatOfficialAccount from '@/actions/App/Actions/Channel/WechatOfficialAccount';
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
import AppLayout from '@/layouts/AppLayout.vue';
import type { ShowWechatOfficialAccountChannelListPagePropsData } from '@/types/generated';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowWechatOfficialAccountChannelListPagePropsData>();
const { t } = useI18n();
const deletingChannelId = ref<string | null>(null);
const deleteForm = useForm({});

const buildChannelListPageUrl = (page: number): string => {
  return WechatOfficialAccount.ListWechatOfficialAccountChannelsAction.url({
    query: { page },
  });
};

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('接入渠道') },
  {
    title: t('微信公众号'),
    href: WechatOfficialAccount.ListWechatOfficialAccountChannelsAction.url(),
  },
  { title: t('列表') },
]);

const selectedChannel = computed(
  () =>
    props.channel_list.find(
      (channel) => channel.id === deletingChannelId.value,
    ) ?? null,
);

const confirmDelete = () => {
  if (!selectedChannel.value || deleteForm.processing) return;

  deleteForm.delete(
    WechatOfficialAccount.DeleteWechatOfficialAccountChannelAction.url({
      channel: selectedChannel.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => (deletingChannelId.value = null),
    },
  );
};
</script>

<template>
  <div class="contents">
    <Head :title="t('微信公众号渠道')" />
    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />
        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            :title="t('微信公众号渠道')"
            :description="
              t('连接微信公众号，让关注者可以直接在公众号中发起咨询。')
            "
          />
          <div class="flex items-center gap-2">
            <Button as-child>
              <Link
                :href="
                  WechatOfficialAccount.ShowCreateWechatOfficialAccountChannelPageAction.url()
                "
                >{{ t('添加渠道') }}</Link
              >
            </Button>
            <Button variant="outline" as-child>
              <Link
                :href="
                  WechatOfficialAccount.ListWechatOfficialAccountChannelTrashAction.url()
                "
                >{{ t('回收站') }}</Link
              >
            </Button>
          </div>
        </div>

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('渠道名称') }}</th>
                  <th class="px-4 py-3">{{ t('AppID') }}</th>
                  <th class="px-4 py-3">{{ t('消息模式') }}</th>
                  <th class="px-4 py-3">{{ t('接待方案') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="channel in props.channel_list"
                  :key="channel.id"
                  class="border-t align-middle"
                >
                  <td class="px-4 py-3 font-medium">{{ channel.name }}</td>
                  <td class="px-4 py-3 font-mono text-muted-foreground">
                    {{ channel.app_id || '—' }}
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{
                      channel.message_mode === null
                        ? t('未配置')
                        : channel.message_mode === 'aes'
                          ? t('安全模式')
                          : t('明文模式')
                    }}
                  </td>
                  <td class="px-4 py-3 text-muted-foreground">
                    {{ channel.reception_plan_name || t('未选择接待方案') }}
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-2">
                      <Button size="sm" variant="outline" as-child>
                        <Link
                          :href="
                            WechatOfficialAccount.ShowWechatOfficialAccountChannelDetailPageAction.url(
                              { channel: channel.id },
                            )
                          "
                          >{{ t('编辑') }}</Link
                        >
                      </Button>
                      <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                          <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :aria-label="t('更多操作')"
                            ><MoreHorizontal class="h-4 w-4"
                          /></Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-36">
                          <DropdownMenuItem
                            class="text-destructive focus:text-destructive"
                            @select="deletingChannelId = channel.id"
                            >{{ t('删除') }}</DropdownMenuItem
                          >
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </td>
                </tr>
                <tr v-if="props.channel_list.length === 0">
                  <td
                    colspan="5"
                    class="px-4 py-8 text-center text-muted-foreground"
                  >
                    {{ t('还没有微信公众号渠道') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <PaginationNavigator
          v-if="props.channel_list_pagination.last_page > 1"
          :pagination="props.channel_list_pagination"
          :page-url="buildChannelListPageUrl"
        />

        <ConfirmDeleteDialog
          :open="deletingChannelId !== null"
          :title="t('删除这个微信公众号渠道？')"
          :detail-title="selectedChannel?.name"
          :detail-description="
            t(
              '删除后会移到回收站，公众号的新消息将无法进入当前应用。恢复后可继续使用。',
            )
          "
          :processing="deleteForm.processing"
          @update:open="(open) => !open && (deletingChannelId = null)"
          @confirm="confirmDelete"
        />
      </div>
    </div>
  </div>
</template>
