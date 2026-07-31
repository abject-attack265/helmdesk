<!--
  网站渠道列表页，使用 ShowWebChannelListPagePropsData 展示渠道和管理入口。
-->
<script setup lang="ts">
import Web from '@/actions/App/Actions/Channel/Web';
import EmbedHostCell from '@/components/channel/EmbedHostCell.vue';
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ShowWebChannelListPagePropsData } from '@/types/generated';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowWebChannelListPagePropsData>();
const { t } = useI18n();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('接入渠道') },
  {
    title: t('网站'),
    href: Web.ListWebChannelsAction.url(),
  },
  { title: t('列表') },
]);

const deleteForm = useForm({});
const deletingChannelId = ref<string | null>(null);

const buildChannelListPageUrl = (page: number): string => {
  return Web.ListWebChannelsAction.url({
    query: { page },
  });
};

const selectedChannel = computed(
  () =>
    props.channel_list.find(
      (channel) => channel.id === deletingChannelId.value,
    ) ?? null,
);

const confirmDelete = () => {
  if (!selectedChannel.value || deleteForm.processing) {
    return;
  }

  deleteForm.delete(
    Web.DeleteWebChannelAction.url({
      channel: selectedChannel.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        deletingChannelId.value = null;
      },
    },
  );
};

const handleDeleteDialogOpenChange = (open: boolean) => {
  if (!open) {
    deletingChannelId.value = null;
  }
};
</script>

<template>
  <div class="contents">
    <Head :title="t('网站渠道')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            :title="t('网站渠道')"
            :description="t('让访客通过网站嵌入或聊天链接发起咨询。')"
          />

          <div class="flex items-center gap-2">
            <Button as-child>
              <Link :href="Web.ShowCreateWebChannelPageAction.url()">
                {{ t('添加网站渠道') }}
              </Link>
            </Button>

            <Button variant="outline" as-child>
              <Link :href="Web.ListWebChannelTrashAction.url()">
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
                  <th class="px-4 py-3">{{ t('渠道名称') }}</th>
                  <th class="px-4 py-3">{{ t('接待方案') }}</th>
                  <th class="px-4 py-3">{{ t('最近接入网站') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="channel in props.channel_list"
                  :key="channel.id"
                  class="border-t bg-background align-middle"
                >
                  <td class="px-4 py-3">
                    <span class="font-medium">{{ channel.name }}</span>
                  </td>

                  <td class="px-4 py-3 text-muted-foreground">
                    <span v-if="channel.reception_plan_name">
                      {{ channel.reception_plan_name }}
                    </span>
                    <span v-else>{{ t('未选择接待方案') }}</span>
                  </td>

                  <td class="px-4 py-3 text-muted-foreground">
                    <EmbedHostCell
                      :host="channel.last_embed_host"
                      :at="channel.last_embed_at"
                    />
                  </td>

                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-2">
                      <Button size="sm" variant="outline" as-child>
                        <Link
                          :href="
                            Web.ShowWebChannelDetailPageAction.url({
                              channel: channel.id,
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
                            @select="deletingChannelId = channel.id"
                          >
                            {{ t('删除') }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </td>
                </tr>

                <tr v-if="props.channel_list.length === 0">
                  <td
                    colspan="4"
                    class="px-4 py-8 text-center text-muted-foreground"
                  >
                    {{ t('还没有网站渠道') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <ConfirmDeleteDialog
          :open="deletingChannelId !== null"
          :title="t('删除这个网站渠道？')"
          :detail-title="selectedChannel?.name"
          :detail-description="
            t(
              '删除后会移到回收站，访客将暂时无法通过这个入口发起会话。恢复后可继续使用。',
            )
          "
          :processing="deleteForm.processing"
          @update:open="handleDeleteDialogOpenChange"
          @confirm="confirmDelete"
        />

        <div
          v-if="props.channel_list_pagination.last_page > 1"
          class="rounded-lg border p-4"
        >
          <PaginationNavigator
            :pagination="props.channel_list_pagination"
            :page-url="buildChannelListPageUrl"
          />
        </div>
      </div>
    </div>
  </div>
</template>
