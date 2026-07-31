<!--
  经验提炼任务列表页，消费 ShowExperienceExtractionListPagePropsData。
  展示指定问答知识库的提炼任务、处理进度和实时状态。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import FilterPopover from '@/components/common/FilterPopover.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import TextLink from '@/components/common/TextLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useKnowledgeBaseExplorerNavigation } from '@/composables/useKnowledgeBaseExplorerNavigation';
import KnowledgeBasesLayout from '@/layouts/KnowledgeBasesLayout.vue';
import { appContentLayout } from '@/layouts/pageLayouts';
import { subscribeReceptionInstance } from '@/lib/mercure';
import KnowledgeBaseExplorerSidebar from '@/pages/knowledgeBase/KnowledgeBaseExplorerSidebar.vue';
import experienceExtraction from '@/routes/app/manage/experience-extraction';
import type { ShowExperienceExtractionListPagePropsData } from '@/types/generated';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

defineOptions({ layout: appContentLayout });

const props = defineProps<ShowExperienceExtractionListPagePropsData>();

const { t } = useI18n();
const { formatDateTime } = useDateTime();
const explorerNavigation = useKnowledgeBaseExplorerNavigation();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('知识库'),
    href: KnowledgeBase.ListKnowledgeBasesAction.url(),
  },
  {
    title: props.knowledge_base.name,
    href: KnowledgeBase.ListKnowledgeBasesAction.url({
      query: { kb: props.knowledge_base.id },
    }),
  },
  { title: t('经验提炼') },
]);

/** 状态筛选；URL 与服务端 props 是真理之源，变更立即 navigate。 */
const filterStatus = ref(props.current_status ?? 'all');
watch(
  () => props.current_status,
  () => {
    filterStatus.value = props.current_status ?? 'all';
  },
);

const activeFilterCount = computed(() =>
  filterStatus.value !== 'all' ? 1 : 0,
);

const applyFilter = () => {
  router.get(
    experienceExtraction.index.url({
      knowledgeBase: props.knowledge_base.id,
    }),
    filterStatus.value !== 'all' ? { status: filterStatus.value } : {},
    { preserveScroll: true, preserveState: true },
  );
};

const clearFilters = () => {
  filterStatus.value = 'all';
  applyFilter();
};

/** 删除任务：进行中的任务禁用；确认后连同其候选一并移除，已采纳的知识库问答不受影响。 */
const deleteForm = useForm({});
const deletingExtractionId = ref<string | null>(null);

const deletingExtraction = computed(
  () =>
    props.extractions.find((e) => e.id === deletingExtractionId.value) ?? null,
);

const confirmDelete = () => {
  if (!deletingExtraction.value || deleteForm.processing) {
    return;
  }

  deleteForm.delete(
    experienceExtraction.destroy.url({
      extraction: deletingExtraction.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        deletingExtractionId.value = null;
      },
    },
  );
};

const handleDeleteDialogOpenChange = (open: boolean) => {
  if (!open) {
    deletingExtractionId.value = null;
  }
};

const buildListPageUrl = (page: number): string =>
  experienceExtraction.index.url(
    {
      knowledgeBase: props.knowledge_base.id,
    },
    {
      query: {
        page,
        ...(filterStatus.value !== 'all' ? { status: filterStatus.value } : {}),
      },
    },
  );

/** 提炼运行结束的应用信号 → 回源刷新任务列表（进度收敛）。 */
let unsubscribe: (() => void) | null = null;
onMounted(() => {
  unsubscribe = subscribeReceptionInstance((payload) => {
    if (payload.event === 'experience_extraction_finished') {
      router.reload();
    }
  });
});
onUnmounted(() => {
  unsubscribe?.();
  unsubscribe = null;
});
</script>

<template>
  <div class="contents">
    <Head :title="t('经验提炼')" />

    <KnowledgeBasesLayout>
      <template #sidebar="{ closeMobileExplorer }">
        <KnowledgeBaseExplorerSidebar
          :knowledge-bases="props.sidebar.knowledge_base_list"
          :category-options="props.sidebar.category_options"
          :active-kb-id="props.knowledge_base.id"
          @navigate="closeMobileExplorer"
          @select-kb="explorerNavigation.openKb"
          @select-group="explorerNavigation.openGroup"
          @create="explorerNavigation.openCreateKb"
          @edit="explorerNavigation.openEditKb"
        />
      </template>

      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            :title="t('经验提炼')"
            :description="
              t(
                '从人工接待会话中批量提炼可复用经验，润色后沉淀为「{name}」的问答对',
                { name: props.knowledge_base.name },
              )
            "
          />

          <Button as-child>
            <Link
              :href="
                experienceExtraction.create.url({
                  knowledgeBase: props.knowledge_base.id,
                })
              "
            >
              {{ t('创建任务') }}
            </Link>
          </Button>
        </div>

        <div class="flex flex-wrap items-end justify-end gap-3">
          <FilterPopover
            :active-count="activeFilterCount"
            @clear="clearFilters"
          >
            <div class="space-y-4 p-3">
              <div class="grid gap-2">
                <Label for="filter-status">{{ t('状态') }}</Label>
                <Select
                  v-model="filterStatus"
                  @update:model-value="applyFilter"
                >
                  <SelectTrigger id="filter-status" class="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">{{ t('全部状态') }}</SelectItem>
                    <SelectItem
                      v-for="option in status_options"
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

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('创建时间') }}</th>
                  <th class="px-4 py-3">{{ t('状态') }}</th>
                  <th class="px-4 py-3">{{ t('会话数') }}</th>
                  <th class="px-4 py-3">{{ t('候选经验') }}</th>
                  <th class="px-4 py-3">{{ t('触发人') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="extraction in props.extractions"
                  :key="extraction.id"
                  class="border-t bg-background align-middle"
                >
                  <td class="px-4 py-3">
                    <span class="font-medium">
                      {{ formatDateTime(extraction.created_at) }}
                    </span>
                  </td>

                  <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-1.5">
                      <Spinner
                        v-if="extraction.status === 'running'"
                        class="h-3 w-3"
                      />
                      <Badge variant="outline">
                        {{ extraction.status_label }}
                      </Badge>
                    </span>
                  </td>

                  <td class="px-4 py-3 text-muted-foreground">
                    <TextLink
                      :href="
                        experienceExtraction.conversations.url({
                          extraction: extraction.id,
                        })
                      "
                    >
                      {{
                        t('{count} 个', {
                          count: extraction.conversation_count,
                        })
                      }}
                    </TextLink>
                  </td>

                  <td class="px-4 py-3 text-muted-foreground">
                    <template v-if="extraction.status === 'failed'">
                      {{ extraction.error }}
                    </template>
                    <template v-else>
                      {{
                        t('{count} 条', { count: extraction.candidate_count })
                      }}
                      <template v-if="extraction.pending_candidate_count > 0">
                        ·
                        {{
                          t('待处理 {count} 条', {
                            count: extraction.pending_candidate_count,
                          })
                        }}
                      </template>
                    </template>
                  </td>

                  <td class="px-4 py-3 text-muted-foreground">
                    {{ extraction.triggered_by_name ?? '-' }}
                  </td>

                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-2">
                      <Button size="sm" variant="outline" as-child>
                        <Link
                          :href="
                            experienceExtraction.results.url({
                              extraction: extraction.id,
                            })
                          "
                        >
                          {{ t('结果') }}
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
                            :disabled="extraction.status === 'running'"
                            :title="
                              extraction.status === 'running'
                                ? t('进行中的任务不能删除')
                                : undefined
                            "
                            @select="
                              extraction.status !== 'running' &&
                              (deletingExtractionId = extraction.id)
                            "
                          >
                            {{ t('删除') }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </td>
                </tr>

                <tr v-if="props.extractions.length === 0">
                  <td
                    colspan="6"
                    class="px-4 py-8 text-center text-muted-foreground"
                  >
                    {{
                      t('暂无提炼任务，点击「创建任务」从人工会话中提取经验')
                    }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="props.extractions_pagination.last_page > 1"
            class="border-t p-4"
          >
            <PaginationNavigator
              :pagination="props.extractions_pagination"
              :page-url="buildListPageUrl"
            />
          </div>
        </div>

        <ConfirmDeleteDialog
          :open="deletingExtractionId !== null"
          :title="t('确认删除提炼任务？')"
          :detail-title="
            deletingExtraction
              ? formatDateTime(deletingExtraction.created_at)
              : undefined
          "
          :detail-description="
            t(
              '删除后该任务及其候选经验会一并移除且不可恢复；已采纳进知识库的问答不受影响。',
            )
          "
          :processing="deleteForm.processing"
          @update:open="handleDeleteDialogOpenChange"
          @confirm="confirmDelete"
        />
      </div>
    </KnowledgeBasesLayout>
  </div>
</template>
