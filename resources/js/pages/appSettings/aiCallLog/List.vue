<!--
  AI 调用日志列表页，消费 ShowAiCallLogListPagePropsData。
  支持按用途、状态和关键词筛选，并在抽屉中查看对话详情。
-->
<script setup lang="ts">
import FilterPopover from '@/components/common/FilterPopover.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type {
  AiCallLogDetailData,
  ListAiCallLogItemData,
  ShowAiCallLogListPagePropsData,
} from '@/types/generated';
import { Head, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import AiCallLogDetailDrawer from './AiCallLogDetailDrawer.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowAiCallLogListPagePropsData>();
const { t } = useI18n();
const { formatDateTime } = useDateTime();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('AI 调用日志'), href: app.manage.aiCallLogs.index.url() },
  { title: t('列表') },
]);

const ALL = 'all';

const searchInput = ref(props.filters.search ?? '');
const purpose = ref(props.filters.purpose ?? ALL);
const status = ref(props.filters.status ?? ALL);

const filterPanelOpen = ref(false);

// 筛选状态与服务端 props 保持同步。
watch(
  () => props.filters,
  (filters) => {
    searchInput.value = filters.search ?? '';
    purpose.value = filters.purpose ?? ALL;
    status.value = filters.status ?? ALL;
  },
);

// 用途和状态各计为一个激活筛选。
const activeFilterCount = computed(
  () => (purpose.value !== ALL ? 1 : 0) + (status.value !== ALL ? 1 : 0),
);

const navigate = (overrides: Record<string, unknown> = {}): void => {
  router.get(
    app.manage.aiCallLogs.index.url(),
    {
      search: searchInput.value || undefined,
      purpose: purpose.value !== ALL ? purpose.value : undefined,
      status: status.value !== ALL ? status.value : undefined,
      ...overrides,
    },
    { preserveState: true, preserveScroll: true, replace: true },
  );
};

let searchTimer: ReturnType<typeof setTimeout> | undefined;
const onSearchInput = (): void => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => navigate(), 350);
};

const clearAllFilters = (): void => {
  purpose.value = ALL;
  status.value = ALL;
  navigate();
};

const pageUrl = (page: number): string =>
  app.manage.aiCallLogs.index.url({
    query: {
      page,
      search: searchInput.value || undefined,
      purpose: purpose.value !== ALL ? purpose.value : undefined,
      status: status.value !== ALL ? status.value : undefined,
    },
  });

// 详情抽屉：点「查看对话」后按 id 异步拉取整段对话
const activeDetail = ref<AiCallLogDetailData | null>(null);
const drawerOpen = ref(false);
const drawerLoading = ref(false);
const drawerError = ref('');

const openDetail = async (log: ListAiCallLogItemData): Promise<void> => {
  drawerOpen.value = true;
  drawerLoading.value = true;
  drawerError.value = '';
  activeDetail.value = null;

  try {
    const response = await axios.get<AiCallLogDetailData>(
      app.manage.aiCallLogs.show.url(log.id),
    );
    activeDetail.value = response.data;
  } catch {
    drawerError.value = t('加载调用详情失败，请重试');
  } finally {
    drawerLoading.value = false;
  }
};

const tokenSummary = (log: ListAiCallLogItemData): string =>
  `${log.input_tokens} / ${log.output_tokens}`;
</script>

<template>
  <div class="contents">
    <Head :title="t('AI 调用日志')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto w-full max-w-none space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <HeadingSmall
          :title="t('AI 调用日志')"
          :description="
            t(
              '每个 AI 会话的完整对话与工具调用。可按会话 / 消息 / 联系人 ID 精确反查，也可直接搜输入 / 输出内容。',
            )
          "
        />

        <!-- 工具条：搜索 + 筛选 -->
        <div class="flex flex-wrap items-end justify-end gap-3">
          <div class="flex items-center gap-3">
            <div class="relative">
              <Search
                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
              />
              <Input
                v-model="searchInput"
                type="text"
                :placeholder="t('搜索 ID 或输入 / 输出内容')"
                class="h-9 w-64 pl-9"
                @input="onSearchInput"
              />
            </div>
            <FilterPopover
              v-model:open="filterPanelOpen"
              :active-count="activeFilterCount"
              @clear="clearAllFilters"
            >
              <div class="space-y-3 p-3">
                <div class="grid gap-2">
                  <Label>{{ t('用途') }}</Label>
                  <Select
                    v-model="purpose"
                    @update:model-value="() => navigate()"
                  >
                    <SelectTrigger class="h-9 w-full">
                      <SelectValue :placeholder="t('用途')" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem :value="ALL">{{ t('全部用途') }}</SelectItem>
                      <SelectItem
                        v-for="option in props.purpose_options"
                        :key="String(option.value)"
                        :value="String(option.value)"
                      >
                        {{ option.label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div class="grid gap-2">
                  <Label>{{ t('状态') }}</Label>
                  <Select
                    v-model="status"
                    @update:model-value="() => navigate()"
                  >
                    <SelectTrigger class="h-9 w-full">
                      <SelectValue :placeholder="t('状态')" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem :value="ALL">{{ t('全部状态') }}</SelectItem>
                      <SelectItem
                        v-for="option in props.status_options"
                        :key="String(option.value)"
                        :value="String(option.value)"
                      >
                        {{ option.label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </FilterPopover>
          </div>
        </div>

        <!-- 表格 -->
        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('时间') }}</th>
                  <th class="px-4 py-3">{{ t('用途') }}</th>
                  <th class="px-4 py-3">{{ t('模型消息') }}</th>
                  <th class="px-4 py-3">{{ t('模型') }}</th>
                  <th class="px-4 py-3">{{ t('状态') }}</th>
                  <th class="px-4 py-3">{{ t('Token（入/出）') }}</th>
                  <th class="px-4 py-3">{{ t('轮次') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="log in props.logs"
                  :key="log.id"
                  class="border-t bg-background hover:bg-muted/40"
                >
                  <td class="px-4 py-3 whitespace-nowrap">
                    {{ formatDateTime(log.last_at) }}
                  </td>
                  <td class="px-4 py-3">{{ log.purpose_label }}</td>
                  <td
                    class="max-w-xs truncate px-4 py-3 text-muted-foreground"
                    :title="log.output_preview ?? ''"
                  >
                    {{ log.output_preview || '—' }}
                  </td>
                  <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                    {{ log.model_name || '—' }}
                  </td>
                  <td class="px-4 py-3">
                    <Badge
                      :variant="
                        log.status === 'error' ? 'destructive' : 'secondary'
                      "
                    >
                      {{ log.status === 'error' ? t('失败') : t('成功') }}
                    </Badge>
                  </td>
                  <td class="px-4 py-3 tabular-nums">
                    {{ tokenSummary(log) }}
                  </td>
                  <td class="px-4 py-3 tabular-nums">{{ log.turn_count }}</td>
                  <td class="px-4 py-3 text-right">
                    <Button
                      variant="outline"
                      size="sm"
                      @click="openDetail(log)"
                    >
                      {{ t('查看对话') }}
                    </Button>
                  </td>
                </tr>

                <tr v-if="props.logs.length === 0">
                  <td
                    class="px-4 py-10 text-center text-muted-foreground"
                    colspan="8"
                  >
                    {{ t('暂无调用日志') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <PaginationNavigator
          v-if="props.pagination.last_page > 1"
          :pagination="props.pagination"
          :page-url="pageUrl"
        />
      </div>
    </div>

    <AiCallLogDetailDrawer
      v-model:open="drawerOpen"
      :detail="activeDetail"
      :loading="drawerLoading"
      :error="drawerError"
    />
  </div>
</template>
