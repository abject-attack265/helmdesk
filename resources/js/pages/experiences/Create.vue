<!--
  经验提炼任务创建页，消费 ShowCreateExperienceExtractionPagePropsData。
  选择联系人、接待方式、时间范围和待分析会话。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import FilterPopover from '@/components/common/FilterPopover.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import ConversationDetailSheet from '@/components/conversation/ConversationDetailSheet.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useKnowledgeBaseExplorerNavigation } from '@/composables/useKnowledgeBaseExplorerNavigation';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import KnowledgeBasesLayout from '@/layouts/KnowledgeBasesLayout.vue';
import { appContentLayout } from '@/layouts/pageLayouts';
import KnowledgeBaseExplorerSidebar from '@/pages/knowledgeBase/KnowledgeBaseExplorerSidebar.vue';
import experienceExtraction from '@/routes/app/manage/experience-extraction';
import type { ShowCreateExperienceExtractionPagePropsData } from '@/types/generated';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Search } from '@lucide/vue';
import dayjs from 'dayjs';
import { computed, ref, watch } from 'vue';

defineOptions({ layout: appContentLayout });

const props = defineProps<ShowCreateExperienceExtractionPagePropsData>();

const { t } = useI18n();
const { formatDateTime } = useDateTime();
const { formatVisitorName } = useVisitorDisplay();
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
  {
    title: t('经验提炼'),
    href: experienceExtraction.index.url({
      knowledgeBase: props.knowledge_base.id,
    }),
  },
  { title: t('创建任务') },
]);

/**
 * 筛选条件；URL 与服务端 props 是真理之源，本地仅暂存输入，变更立即 navigate。
 * 时间窗口始终有值（服务端缺省给最近 max_window_days 天，超跨度会收敛后回显）。
 */
const filterFrom = ref(props.window.from);
const filterTo = ref(props.window.to);
const filterTeammate = ref(props.filter_teammate_user_id ?? 'all');
const filterSearch = ref(props.filter_search ?? '');
watch(
  () => [
    props.window.from,
    props.window.to,
    props.filter_teammate_user_id,
    props.filter_search,
  ],
  () => {
    filterFrom.value = props.window.from;
    filterTo.value = props.window.to;
    filterTeammate.value = props.filter_teammate_user_id ?? 'all';
    filterSearch.value = props.filter_search ?? '';
  },
);

/** 时间窗口是必选项且总有值，不计入激活数；只数在它之外额外收窄的条件。 */
const activeFilterCount = computed(() =>
  filterTeammate.value !== 'all' ? 1 : 0,
);

/**
 * 日期输入的可选范围：结束日期不能早于开始日期，两者跨度不超过 max_window_days 天。
 *
 * 窗口边界是不带时区的日历日期，全程用 dayjs 在本地时区加减并格式化；
 * 换算成 UTC 再截字符串会让东八区的用户少掉一天。
 */
const shiftDays = (date: string, days: number): string =>
  dayjs(date).add(days, 'day').format('YYYY-MM-DD');
const toMin = computed(() => filterFrom.value);
const toMax = computed(() =>
  shiftDays(filterFrom.value, props.max_window_days - 1),
);

/** 筛选变更后回到第一页，避免停留在超出新结果集的页码上。 */
const applyFilter = () => {
  router.get(
    experienceExtraction.create.url({
      knowledgeBase: props.knowledge_base.id,
    }),
    {
      from: filterFrom.value,
      to: filterTo.value,
      ...(filterTeammate.value !== 'all'
        ? { teammate: filterTeammate.value }
        : {}),
      ...(filterSearch.value.trim()
        ? { search: filterSearch.value.trim() }
        : {}),
    },
    { preserveScroll: true, preserveState: true },
  );
};

/** 翻页链接取服务端已生效的筛选条件，而非本地 ref（后者可能含未提交的输入）。 */
const buildPageUrl = (page: number): string =>
  experienceExtraction.create.url(
    {
      knowledgeBase: props.knowledge_base.id,
    },
    {
      query: {
        page,
        from: props.window.from,
        to: props.window.to,
        ...(props.filter_teammate_user_id
          ? { teammate: props.filter_teammate_user_id }
          : {}),
        ...(props.filter_search ? { search: props.filter_search } : {}),
      },
    },
  );

const clearFilters = () => {
  filterTeammate.value = 'all';
  applyFilter();
};

/**
 * 勾选状态：默认不勾选（触发提炼是批量且不可撤销的操作，交由管理员显式选择）。
 * 翻页保留跨页勾选，仅在筛选条件变化（候选集本身改变）时清空。
 * 值是联系人 ID，会话数单独记着好累计上限——勾选的是人，送去分析的是他窗口内的全部会话。
 */
const selectedConversationCounts = ref<Map<string, number>>(new Map());
watch(
  () => [
    props.window.from,
    props.window.to,
    props.filter_teammate_user_id,
    props.filter_search,
  ],
  () => {
    selectedConversationCounts.value = new Map();
  },
);

const selectedContactCount = computed(
  () => selectedConversationCounts.value.size,
);
const selectedConversationCount = computed(() =>
  [...selectedConversationCounts.value.values()].reduce(
    (total, count) => total + count,
    0,
  ),
);

const toggleContact = (
  id: string,
  conversationCount: number,
  checked: boolean,
) => {
  const next = new Map(selectedConversationCounts.value);
  if (checked) {
    next.set(id, conversationCount);
  } else {
    next.delete(id);
  }
  selectedConversationCounts.value = next;
};

const selectUnextracted = () => {
  const next = new Map(selectedConversationCounts.value);
  props.selectable_contacts
    .filter((contact) => !contact.already_extracted)
    .forEach((contact) => next.set(contact.id, contact.conversation_count));
  selectedConversationCounts.value = next;
};
const clearSelection = () => {
  selectedConversationCounts.value = new Map();
};

/** 勾选可跨页累积到超过单次运行的会话上限，前端先行拦截（后端也会再校验一次）。 */
const isOverLimit = computed(
  () => selectedConversationCount.value > props.max_conversations,
);

/** 展开中的联系人：展开后能看到这个人窗口内具体哪些会话会被送进分析。 */
const expandedContactIds = ref<Set<string>>(new Set());
const toggleExpanded = (id: string) => {
  const next = new Set(expandedContactIds.value);
  if (next.has(id)) {
    next.delete(id);
  } else {
    next.add(id);
  }
  expandedContactIds.value = next;
};

/** 会话详情抽屉（整页布局下的唯一浮层）。 */
const viewingConversationId = ref<string | null>(null);

/**
 * 对勾选的联系人在绑定问答库下触发一次提炼运行；服务端成功后重定向回任务列表页。
 * 窗口随表单提交：服务端据此展开成会话集合，保证送去提炼的就是页面上看到的那批。
 */
const startForm = useForm({});
const startExtraction = () => {
  startForm
    .transform(() => ({
      contact_ids: [...selectedConversationCounts.value.keys()],
      from: props.window.from,
      to: props.window.to,
    }))
    .post(
      experienceExtraction.start.url({
        knowledgeBase: props.knowledge_base.id,
      }),
      {
        preserveScroll: true,
      },
    );
};
</script>

<template>
  <div class="contents">
    <Head :title="t('创建任务')" />

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
            :title="t('创建任务')"
            :description="
              t(
                '勾选要分析的联系人，每人在所选时间段内的会话会连起来分析，单次最多 {max} 个会话',
                { max: props.max_conversations },
              )
            "
          />
          <Button
            :disabled="
              has_running_extraction ||
              startForm.processing ||
              selectedContactCount === 0 ||
              isOverLimit
            "
            @click="startExtraction"
          >
            {{ t('开始提炼') }}
          </Button>
        </div>

        <div class="flex flex-wrap items-end justify-end gap-3">
          <div class="relative">
            <Search
              class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
              v-model="filterSearch"
              class="h-9 w-48 pl-9 lg:w-64"
              :placeholder="t('搜索主题或摘要')"
              @keydown.enter.prevent="applyFilter"
            />
          </div>
          <FilterPopover
            :active-count="activeFilterCount"
            @clear="clearFilters"
          >
            <div class="space-y-4 p-3">
              <div class="grid gap-2">
                <Label for="filter-from" required>{{ t('开始日期') }}</Label>
                <Input
                  id="filter-from"
                  v-model="filterFrom"
                  type="date"
                  required
                  class="w-full"
                  @change="applyFilter"
                />
              </div>
              <div class="grid gap-2">
                <Label for="filter-to" required>{{ t('结束日期') }}</Label>
                <Input
                  id="filter-to"
                  v-model="filterTo"
                  type="date"
                  required
                  :min="toMin"
                  :max="toMax"
                  class="w-full"
                  @change="applyFilter"
                />
                <p class="text-xs text-muted-foreground">
                  {{
                    t('时间跨度最多 {days} 天，超出会自动收敛', {
                      days: props.max_window_days,
                    })
                  }}
                </p>
              </div>
              <div class="grid gap-2">
                <Label for="filter-teammate">{{ t('客服') }}</Label>
                <Select
                  v-model="filterTeammate"
                  @update:model-value="applyFilter"
                >
                  <SelectTrigger id="filter-teammate" class="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">{{ t('全部客服') }}</SelectItem>
                    <SelectItem
                      v-for="teammate in teammate_options"
                      :key="teammate.id"
                      :value="teammate.id"
                    >
                      {{ teammate.name }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                  {{
                    t(
                      '只筛出该客服服务过的联系人；提炼时仍会带上这些联系人的全部会话',
                    )
                  }}
                </p>
              </div>
            </div>
          </FilterPopover>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <Button
            variant="ghost"
            size="sm"
            :disabled="selectable_contacts.length === 0"
            @click="selectUnextracted"
          >
            {{ t('全选本页未提炼') }}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            :disabled="selectedContactCount === 0"
            @click="clearSelection"
          >
            {{ t('清空选择') }}
          </Button>
          <p class="text-sm text-muted-foreground">
            {{
              t('已选 {contacts} 个联系人 · 共 {conversations} 个会话', {
                contacts: selectedContactCount,
                conversations: selectedConversationCount,
              })
            }}
            <template v-if="isOverLimit">
              ·
              {{
                t('单次最多提炼 {max} 个会话，请减少勾选', {
                  max: max_conversations,
                })
              }}
            </template>
            <template v-if="has_running_extraction">
              · {{ t('已有一次提炼正在进行中，请等待其完成') }}
            </template>
          </p>
        </div>

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="w-10 px-4 py-3" />
                  <th class="px-4 py-3">{{ t('联系人') }}</th>
                  <th class="px-4 py-3">{{ t('会话数') }}</th>
                  <th class="px-4 py-3">{{ t('人工消息') }}</th>
                  <th class="px-4 py-3">{{ t('最近关闭') }}</th>
                  <th class="w-10 px-4 py-3" />
                </tr>
              </thead>
              <tbody>
                <template
                  v-for="contact in selectable_contacts"
                  :key="contact.id"
                >
                  <tr class="border-t bg-background align-middle">
                    <td class="px-4 py-3">
                      <Checkbox
                        :model-value="
                          selectedConversationCounts.has(contact.id)
                        "
                        :aria-label="t('选择联系人')"
                        @update:model-value="
                          (checked) =>
                            toggleContact(
                              contact.id,
                              contact.conversation_count,
                              checked === true,
                            )
                        "
                      />
                    </td>
                    <td class="px-4 py-3">
                      <span class="inline-flex items-center gap-2">
                        <span class="font-medium">
                          {{ formatVisitorName(contact.name, contact.id) }}
                        </span>
                        <Badge
                          v-if="contact.already_extracted"
                          variant="secondary"
                          class="font-normal"
                        >
                          {{ t('已提炼过') }}
                        </Badge>
                      </span>
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">
                      {{
                        t('{count} 个', { count: contact.conversation_count })
                      }}
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">
                      {{
                        t('{count} 条', {
                          count: contact.teammate_message_count,
                        })
                      }}
                    </td>
                    <td class="px-4 py-3 text-muted-foreground">
                      {{ formatDateTime(contact.last_closed_at) }}
                    </td>
                    <td class="px-4 py-3">
                      <Button
                        variant="ghost"
                        size="icon"
                        :aria-label="t('展开会话明细')"
                        @click="toggleExpanded(contact.id)"
                      >
                        <ChevronDown
                          v-if="expandedContactIds.has(contact.id)"
                          class="h-4 w-4"
                        />
                        <ChevronRight v-else class="h-4 w-4" />
                      </Button>
                    </td>
                  </tr>

                  <tr
                    v-if="expandedContactIds.has(contact.id)"
                    class="border-t bg-muted/20"
                  >
                    <td colspan="6" class="px-4 py-3">
                      <ul class="space-y-2">
                        <li
                          v-for="conversation in contact.conversations"
                          :key="conversation.id"
                          class="flex flex-wrap items-center gap-x-3 gap-y-1"
                        >
                          <span class="font-medium">
                            {{ conversation.subject || t('（无主题）') }}
                          </span>
                          <Badge
                            v-if="conversation.already_extracted"
                            variant="secondary"
                            class="font-normal"
                          >
                            {{ t('已提炼过') }}
                          </Badge>
                          <span class="text-muted-foreground">
                            {{ formatDateTime(conversation.closed_at) }}
                          </span>
                          <span class="text-muted-foreground">
                            {{
                              t('人工 {count} 条', {
                                count: conversation.teammate_message_count,
                              })
                            }}
                          </span>
                          <Button
                            size="sm"
                            variant="outline"
                            @click="viewingConversationId = conversation.id"
                          >
                            {{ t('查看') }}
                          </Button>
                        </li>
                      </ul>
                    </td>
                  </tr>
                </template>

                <tr v-if="selectable_contacts.length === 0">
                  <td
                    colspan="6"
                    class="px-4 py-8 text-center text-muted-foreground"
                  >
                    {{ t('当前筛选条件下没有人工参与过的联系人') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="props.selectable_pagination.last_page > 1"
            class="border-t p-4"
          >
            <PaginationNavigator
              :pagination="props.selectable_pagination"
              :page-url="buildPageUrl"
              preserve-state
            />
          </div>
        </div>
      </div>
    </KnowledgeBasesLayout>

    <ConversationDetailSheet v-model="viewingConversationId" />
  </div>
</template>
