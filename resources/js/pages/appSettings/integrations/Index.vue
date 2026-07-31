<!-- 集成列表页，使用 ShowInstanceIntegrationsPagePropsData 查看连接、检查状态并更新工具。 -->
<script setup lang="ts">
import Integration from '@/actions/App/Actions/Integration';
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { useI18n } from '@/composables/useI18n';
import { useToast } from '@/composables/useToast';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
  IntegrationConnectionCheckData,
  IntegrationData,
  IntegrationToolData,
  ShowInstanceIntegrationsPagePropsData,
} from '@/types/generated';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { LoaderCircle, MoreHorizontal } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowInstanceIntegrationsPagePropsData>();

const { t } = useI18n();
const { toast } = useToast();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('设置') },
  {
    title: t('集成'),
    href: Integration.ShowInstanceIntegrationsAction.url(),
  },
  { title: t('列表') },
]);

// 同步状态最多轮询五分钟。
const SYNC_POLL_INTERVAL_MS = 2000;
const MAX_SYNC_POLL_ATTEMPTS = 150;

const deleteForm = useForm({});
const deletingServerSlug = ref<string | null>(null);
const checkingServerSlug = ref<string | null>(null);
const isQueueingSync = ref(false);
const pollingTimer = ref<number | null>(null);
const syncPollAttempts = ref(0);
const syncPollingTimedOut = ref(false);

const deletingServer = computed(
  () =>
    props.servers.find((server) => server.slug === deletingServerSlug.value) ??
    null,
);

const hasSyncingServer = computed(() =>
  props.servers.some((server) => server.last_sync_status === 'syncing'),
);

const isSyncButtonDisabled = computed(
  () =>
    isQueueingSync.value ||
    (hasSyncingServer.value && !syncPollingTimedOut.value) ||
    props.servers.length === 0,
);

function toolDescription(tool: IntegrationToolData): string {
  return tool.description ?? t('暂无说明');
}

function openDeleteDialog(server: IntegrationData): void {
  deletingServerSlug.value = server.slug;
}

function handleDeleteDialogOpenChange(open: boolean): void {
  if (!open && !deleteForm.processing) {
    deletingServerSlug.value = null;
  }
}

function confirmDelete(): void {
  if (!deletingServer.value || deleteForm.processing) {
    return;
  }

  deleteForm.delete(
    Integration.DeleteIntegrationAction.url({
      server: deletingServer.value.slug,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        deletingServerSlug.value = null;
      },
    },
  );
}

async function checkConnection(server: IntegrationData): Promise<void> {
  checkingServerSlug.value = server.slug;

  try {
    const { data } = await axios.post<IntegrationConnectionCheckData>(
      Integration.CheckIntegrationAction[
        '/app/manage/integrations/{server}/check'
      ].url({ server: server.slug }),
    );

    if (data.success) {
      toast.success(data.message);
    } else {
      toast.error(data.message);
    }
  } catch {
    // 请求错误由统一响应处理器提示。
  } finally {
    checkingServerSlug.value = null;
  }
}

function reloadServers(onFinish?: () => void): void {
  router.reload({
    only: ['servers'],
    onFinish,
  });
}

function clearPollingTimer(): void {
  if (pollingTimer.value !== null) {
    window.clearTimeout(pollingTimer.value);
    pollingTimer.value = null;
  }
}

function scheduleSyncPolling(): void {
  if (typeof window === 'undefined') {
    return;
  }

  clearPollingTimer();

  if (!hasSyncingServer.value) {
    syncPollAttempts.value = 0;
    syncPollingTimedOut.value = false;
    return;
  }

  if (syncPollAttempts.value >= MAX_SYNC_POLL_ATTEMPTS) {
    if (!syncPollingTimedOut.value) {
      syncPollingTimedOut.value = true;
      toast.warning(t('暂时没有获取到更新结果，请重新检查。'));
    }

    return;
  }

  pollingTimer.value = window.setTimeout(() => {
    syncPollAttempts.value += 1;
    reloadServers(() => window.setTimeout(scheduleSyncPolling, 0));
  }, SYNC_POLL_INTERVAL_MS);
}

async function syncAllTools(): Promise<void> {
  if (isSyncButtonDisabled.value) {
    return;
  }

  isQueueingSync.value = true;
  syncPollingTimedOut.value = false;

  try {
    await axios.post(Integration.SyncAllIntegrationToolsAction.url());
    reloadServers(() => window.setTimeout(scheduleSyncPolling, 0));
  } catch {
    // 请求错误由统一响应处理器提示。
  } finally {
    isQueueingSync.value = false;
  }
}

function recheckSyncStatus(): void {
  isQueueingSync.value = true;
  syncPollingTimedOut.value = false;
  syncPollAttempts.value = 0;

  reloadServers(() => {
    isQueueingSync.value = false;
    scheduleSyncPolling();
  });
}

function handleSyncAction(): void {
  if (syncPollingTimedOut.value) {
    recheckSyncStatus();
    return;
  }

  void syncAllTools();
}

// 存在同步中的服务时持续刷新列表。
watch(
  () => props.servers.map((server) => server.last_sync_status).join('|'),
  scheduleSyncPolling,
  { immediate: true },
);

onBeforeUnmount(clearPollingTimer);
</script>

<template>
  <div class="contents">
    <Head :title="t('集成')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            :title="t('集成')"
            :description="t('连接外部系统，让 AI 和客服使用其中的工具和数据。')"
          />

          <div class="flex items-center gap-2">
            <Button as-child>
              <Link :href="Integration.ShowCreateIntegrationPageAction.url()">
                {{ t('添加集成') }}
              </Link>
            </Button>
            <Button
              variant="outline"
              :disabled="isSyncButtonDisabled"
              @click="handleSyncAction"
            >
              {{
                syncPollingTimedOut
                  ? t('重新检查')
                  : isQueueingSync || hasSyncingServer
                    ? t('更新中')
                    : t('更新工具')
              }}
            </Button>
          </div>
        </div>

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('集成名称') }}</th>
                  <th class="px-4 py-3">{{ t('类型') }}</th>
                  <th class="px-4 py-3">{{ t('服务地址') }}</th>
                  <th class="px-4 py-3">{{ t('验证方式') }}</th>
                  <th class="px-4 py-3">{{ t('工具') }}</th>
                  <th class="w-56 px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="server in props.servers" :key="server.id">
                  <tr class="border-t bg-background align-middle">
                    <td class="px-4 py-3">
                      <span class="font-medium">{{ server.name }}</span>
                    </td>

                    <td class="px-4 py-3">
                      <Badge variant="secondary">
                        {{ server.provider_label }}
                      </Badge>
                    </td>

                    <td class="max-w-md px-4 py-3">
                      <span class="block truncate text-muted-foreground">
                        {{ server.endpoint_url }}
                      </span>
                    </td>

                    <td class="px-4 py-3 text-muted-foreground">
                      {{ server.auth_method_label }}
                    </td>

                    <td class="px-4 py-3">
                      <div class="flex items-center gap-2">
                        <Popover>
                          <PopoverTrigger as-child>
                            <button
                              type="button"
                              class="inline-flex items-center text-left"
                            >
                              <span
                                class="font-medium underline-offset-4 hover:underline"
                              >
                                {{ server.tools_count }}
                              </span>
                            </button>
                          </PopoverTrigger>
                          <PopoverContent
                            align="start"
                            side="bottom"
                            class="w-96 max-w-[calc(100vw-2rem)] p-0"
                          >
                            <div
                              v-if="server.tools.length > 0"
                              class="max-h-80 divide-y overflow-y-auto"
                            >
                              <div
                                v-for="tool in server.tools"
                                :key="tool.id"
                                class="px-4 py-3"
                              >
                                <div class="flex items-center gap-2">
                                  <span class="font-mono text-sm font-medium">
                                    {{ tool.name }}
                                  </span>
                                  <Badge
                                    v-if="tool.removed_at"
                                    variant="secondary"
                                    class="text-[10px]"
                                  >
                                    {{ t('已下线') }}
                                  </Badge>
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">
                                  {{ toolDescription(tool) }}
                                </p>
                              </div>
                            </div>

                            <div
                              v-else
                              class="px-4 py-6 text-sm text-muted-foreground"
                            >
                              {{ t('还没有可用工具') }}
                            </div>
                          </PopoverContent>
                        </Popover>

                        <LoaderCircle
                          v-if="server.last_sync_status === 'syncing'"
                          class="h-3.5 w-3.5 animate-spin text-muted-foreground"
                        />
                        <TooltipProvider
                          v-else-if="server.last_sync_status === 'failed'"
                        >
                          <Tooltip>
                            <TooltipTrigger as-child>
                              <span
                                class="cursor-default text-xs font-medium text-destructive"
                              >
                                {{ server.last_sync_status_label }}
                              </span>
                            </TooltipTrigger>
                            <TooltipContent
                              v-if="server.last_sync_error"
                              class="max-w-xs break-words"
                            >
                              {{ server.last_sync_error }}
                            </TooltipContent>
                          </Tooltip>
                        </TooltipProvider>
                        <span v-else class="text-xs text-muted-foreground">
                          {{ server.last_sync_status_label }}
                        </span>
                      </div>
                    </td>

                    <td class="w-56 px-4 py-3">
                      <div class="flex justify-end gap-2 whitespace-nowrap">
                        <Button
                          type="button"
                          size="sm"
                          variant="outline"
                          :disabled="checkingServerSlug === server.slug"
                          @click="checkConnection(server)"
                        >
                          <LoaderCircle
                            v-if="checkingServerSlug === server.slug"
                            class="mr-2 h-4 w-4 animate-spin"
                          />
                          {{ t('测试连接') }}
                        </Button>

                        <Button size="sm" variant="outline" as-child>
                          <Link
                            :href="
                              Integration.ShowEditIntegrationPageAction.url({
                                server: server.slug,
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
                              @select="openDeleteDialog(server)"
                            >
                              {{ t('删除') }}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </div>
                    </td>
                  </tr>
                </template>

                <tr v-if="props.servers.length === 0">
                  <td
                    colspan="6"
                    class="px-4 py-8 text-center text-muted-foreground"
                  >
                    {{ t('还没有添加集成') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <ConfirmDeleteDialog
          :open="deletingServerSlug !== null"
          :title="
            t('删除集成 “{name}”？', {
              name: deletingServer?.name ?? '',
            })
          "
          :detail-description="
            t('删除后，其中的 {count} 个工具也会一并移除。', {
              count: deletingServer?.tools_count ?? 0,
            })
          "
          :processing="deleteForm.processing"
          @update:open="handleDeleteDialogOpenChange"
          @confirm="confirmDelete"
        />
      </div>
    </div>
  </div>
</template>
