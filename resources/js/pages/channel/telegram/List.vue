<!--
  Telegram 渠道列表页，使用 ShowTelegramChannelListPagePropsData 展示渠道和管理入口。
-->
<script setup lang="ts">
import Telegram from '@/actions/App/Actions/Channel/Telegram';
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
import type { ShowTelegramChannelListPagePropsData } from '@/types/generated';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowTelegramChannelListPagePropsData>();
const { t } = useI18n();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('接入渠道') },
  {
    title: t('Telegram'),
    href: Telegram.ListTelegramChannelsAction.url(),
  },
  { title: t('列表') },
]);

const deleteForm = useForm({});
const deletingChannelId = ref<string | null>(null);

const buildChannelListPageUrl = (page: number): string => {
  return Telegram.ListTelegramChannelsAction.url({
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
    Telegram.DeleteTelegramChannelAction.url({
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
    <Head :title="t('Telegram 渠道')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            :title="t('Telegram 渠道')"
            :description="
              t('连接 Telegram 机器人，让访客可以在 Telegram 中发起咨询。')
            "
          />

          <div class="flex items-center gap-2">
            <Button as-child>
              <Link :href="Telegram.ShowCreateTelegramChannelPageAction.url()">
                {{ t('添加渠道') }}
              </Link>
            </Button>

            <Button variant="outline" as-child>
              <Link :href="Telegram.ListTelegramChannelTrashAction.url()">
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
                  <th class="px-4 py-3">{{ t('机器人') }}</th>
                  <th class="px-4 py-3">{{ t('接待方案') }}</th>
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
                    <span v-if="channel.bot_username">
                      @{{ channel.bot_username }}
                    </span>
                    <span v-else>—</span>
                  </td>

                  <td class="px-4 py-3 text-muted-foreground">
                    <span v-if="channel.reception_plan_name">
                      {{ channel.reception_plan_name }}
                    </span>
                    <span v-else>{{ t('未选择接待方案') }}</span>
                  </td>

                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-2">
                      <Button size="sm" variant="outline" as-child>
                        <Link
                          :href="
                            Telegram.ShowTelegramChannelDetailPageAction.url({
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
                    {{ t('还没有 Telegram 渠道') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <ConfirmDeleteDialog
          :open="deletingChannelId !== null"
          :title="t('删除这个 Telegram 渠道？')"
          :detail-title="selectedChannel?.name"
          :detail-description="
            t('删除后会移到回收站，不会再开始新的会话；进行中的会话仍可继续。')
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
