<!--
  收件箱工具栏消费 InboxView、InboxTabCountsData、渠道和成员选项，
  呈现筛选状态并提交导航意图。
-->
<script setup lang="ts">
import FilterPopover from '@/components/common/FilterPopover.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useI18n } from '@/composables/useI18n';
import type { InboxNavigationOverrides } from '@/pages/inbox/inboxNavigation';
import type { AppPageProps } from '@/types';
import type {
  EnabledWebChannelData,
  InboxTabCountsData,
  InboxView,
  UserOptionData,
} from '@/types/generated';
import { usePage } from '@inertiajs/vue3';
import { ChevronDown, Search, X } from '@lucide/vue';
import { computed, ref } from 'vue';

interface SearchScopeContact {
  id: string;
  name: string;
}

interface Props {
  currentView: InboxView;
  currentChannelId: string | null;
  currentAssignee: string | null;
  searchValue: string;
  currentImportantOnly: boolean;
  enabledWebChannels: EnabledWebChannelData[];
  teammates: UserOptionData[];
  tabCounts: InboxTabCountsData;
  searchPanelActive: boolean;
  scopeContact: SearchScopeContact | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (event: 'update:searchPanelActive', value: boolean): void;
  (event: 'removeScope'): void;
  (event: 'searchInput', search: string): void;
  (event: 'searchEnter'): void;
  (event: 'searchExit'): void;
  (event: 'navigate', overrides: InboxNavigationOverrides): void;
}>();

const { t } = useI18n();
const page = usePage<AppPageProps>();

const ANY_VALUE = '__any__';
const UNASSIGNED_VALUE = 'unassigned';

interface TabDefinition {
  view: InboxView;
  label: string;
  count: number | null;
}

const primaryTabs = computed<TabDefinition[]>(() => [
  { view: 'pending', label: t('排队中'), count: props.tabCounts.pending },
  { view: 'mine', label: t('我负责的'), count: props.tabCounts.mine },
  { view: 'ai', label: t('AI 接待中'), count: props.tabCounts.ai },
]);

const moreTabs = computed<TabDefinition[]>(() => [
  { view: 'teammates', label: t('同事'), count: props.tabCounts.teammates },
  { view: 'closed', label: t('已关闭'), count: null },
]);

const isMoreView = computed(() =>
  moreTabs.value.some((tab) => tab.view === props.currentView),
);

const activeMoreTab = computed<TabDefinition | null>(
  () => moreTabs.value.find((tab) => tab.view === props.currentView) ?? null,
);

const moreTriggerLabel = computed(
  () => activeMoreTab.value?.label ?? t('更多'),
);

const moreCount = computed(() =>
  moreTabs.value.reduce((total, tab) => total + (tab.count ?? 0), 0),
);

const moreTriggerCount = computed(() =>
  activeMoreTab.value ? activeMoreTab.value.count : moreCount.value,
);

const channelSelectValue = computed(() => props.currentChannelId ?? ANY_VALUE);
const assigneeSelectValue = computed(() => props.currentAssignee ?? ANY_VALUE);

const activeFilterCount = computed(() => {
  let count = 0;
  if (props.currentChannelId) count += 1;
  if (props.currentAssignee) count += 1;
  if (props.currentImportantOnly) count += 1;
  return count;
});

function navigate(overrides: InboxNavigationOverrides): void {
  emit('navigate', overrides);
}

function selectView(view: InboxView): void {
  if (view === props.currentView) {
    return;
  }
  navigate({
    filters: { view },
    threadId: null,
    pane: null,
  });
}

function requireSelectString(value: unknown, field: string): string {
  if (typeof value !== 'string') {
    throw new Error(`收件箱${field}筛选值必须是字符串`);
  }

  return value;
}

function onChannelChange(value: unknown): void {
  const selectedValue = requireSelectString(value, '渠道');
  const channel = selectedValue === ANY_VALUE ? null : selectedValue;
  navigate({
    filters: { channelId: channel },
    threadId: null,
    pane: null,
  });
}

function onAssigneeChange(value: unknown): void {
  const selectedValue = requireSelectString(value, '负责人');
  const assignee = selectedValue === ANY_VALUE ? null : selectedValue;
  navigate({
    filters: { assignee },
    threadId: null,
    pane: null,
  });
}

function onImportantOnlyChange(value: boolean): void {
  navigate({
    filters: { importantOnly: value },
    threadId: null,
    pane: null,
  });
}

function onSearchInput(value: string | number): void {
  emit('searchInput', String(value));
}

function onSearchEnter(event: KeyboardEvent): void {
  if (event.isComposing) return;
  event.preventDefault();
  emit('searchEnter');
}

function onSearchFocus(): void {
  if (!props.searchPanelActive) {
    emit('update:searchPanelActive', true);
  }
}

function exitSearchPanel(): void {
  emit('searchExit');
  searchInputRef.value?.$el?.blur();
}

const searchInputRef = ref<{ $el?: HTMLInputElement } | null>(null);

function focusSearchInput(): void {
  searchInputRef.value?.$el?.focus();
}

defineExpose({ focusSearchInput });

function clearFilters(): void {
  if (activeFilterCount.value === 0) return;
  navigate({
    filters: {
      channelId: null,
      assignee: null,
      importantOnly: false,
    },
    threadId: null,
    pane: null,
  });
}

function formatTabCount(value: number): string {
  if (value > 99) return '99+';
  return String(value);
}
</script>

<template>
  <div class="flex shrink-0 flex-col border-b">
    <div class="px-2 pt-2" :class="{ 'pb-2': props.searchPanelActive }">
      <div class="flex items-center gap-2">
        <div class="relative min-w-0 flex-1">
          <Search
            class="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
          />
          <Input
            ref="searchInputRef"
            :model-value="props.searchValue"
            class="h-8 pr-8 pl-8 text-xs"
            @update:model-value="onSearchInput"
            @keydown.enter="onSearchEnter"
            @keydown.escape="exitSearchPanel"
            @focus="onSearchFocus"
          />
          <Button
            v-if="props.searchPanelActive || props.searchValue"
            type="button"
            variant="ghost"
            size="icon"
            class="absolute top-1/2 right-1 size-6 -translate-y-1/2"
            :aria-label="t('取消搜索')"
            :title="t('取消搜索')"
            @click="exitSearchPanel"
          >
            <X class="size-3.5" />
          </Button>
        </div>

        <FilterPopover
          v-if="!props.searchPanelActive"
          :active-count="activeFilterCount"
          badge-variant="secondary"
          badge-class="h-4 min-w-4 text-[10px]"
          icon-class="size-3.5"
          trigger-class="h-8 shrink-0 gap-1 px-2 text-xs"
          :side-offset="4"
          @clear="clearFilters"
        >
          <div class="space-y-3 p-3">
            <div class="space-y-1.5">
              <Label class="text-xs text-muted-foreground">
                {{ t('渠道') }}
              </Label>
              <Select
                :model-value="channelSelectValue"
                @update:model-value="onChannelChange"
              >
                <SelectTrigger class="h-8 w-full text-xs">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="ANY_VALUE">
                    {{ t('全部渠道') }}
                  </SelectItem>
                  <SelectItem
                    v-for="channel in props.enabledWebChannels"
                    :key="channel.id"
                    :value="channel.id"
                  >
                    {{ channel.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-1.5">
              <Label class="text-xs text-muted-foreground">
                {{ t('负责人') }}
              </Label>
              <Select
                :model-value="assigneeSelectValue"
                @update:model-value="onAssigneeChange"
              >
                <SelectTrigger class="h-8 w-full text-xs">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem :value="ANY_VALUE">
                    {{ t('全部负责人') }}
                  </SelectItem>
                  <SelectItem :value="UNASSIGNED_VALUE">
                    {{ t('未分配') }}
                  </SelectItem>
                  <SelectItem :value="page.props.auth.user.id">
                    {{ page.props.auth.user.name }}
                  </SelectItem>
                  <SelectItem
                    v-for="teammate in props.teammates"
                    :key="teammate.id"
                    :value="teammate.id"
                  >
                    {{ teammate.name }}
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex items-center justify-between gap-3 pt-1">
              <Label for="inbox-important-only" class="text-xs">
                {{ t('仅重点客户') }}
              </Label>
              <Switch
                id="inbox-important-only"
                :model-value="props.currentImportantOnly"
                @update:model-value="onImportantOnlyChange"
              />
            </div>
          </div>
        </FilterPopover>
      </div>

      <template v-if="props.searchPanelActive && props.scopeContact">
        <div class="pt-2 text-[11px] text-muted-foreground">
          {{ t('仅搜索该联系人的消息') }}
        </div>
        <div class="pt-1.5">
          <span
            class="inline-flex max-w-full items-center gap-1 rounded-full border bg-muted/50 py-0.5 pr-1 pl-2.5 text-xs"
          >
            <span class="min-w-0 truncate">{{ props.scopeContact.name }}</span>
            <button
              type="button"
              class="inline-flex size-4 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
              :aria-label="t('搜索所有联系人和消息')"
              :title="t('搜索所有联系人和消息')"
              @click="emit('removeScope')"
            >
              <X class="size-3" />
            </button>
          </span>
        </div>
      </template>
    </div>

    <div v-if="!props.searchPanelActive" class="pt-2">
      <div class="flex items-stretch" role="tablist">
        <div class="flex min-w-0 flex-1 items-stretch justify-between pl-2">
          <button
            v-for="tab in primaryTabs"
            :key="tab.view"
            type="button"
            role="tab"
            :aria-selected="tab.view === props.currentView"
            :title="
              tab.count !== null && tab.count > 0
                ? `${tab.label} (${tab.count})`
                : tab.label
            "
            class="relative h-8 min-w-0 rounded-md px-1 text-center text-sm font-medium transition-colors"
            :class="
              tab.view === props.currentView
                ? 'text-foreground'
                : 'text-muted-foreground hover:text-foreground'
            "
            @click="selectView(tab.view)"
          >
            <span class="block truncate">{{ tab.label }}</span>
            <Badge
              v-if="tab.count !== null && tab.count > 0"
              :variant="
                tab.view === props.currentView ? 'default' : 'secondary'
              "
              class="pointer-events-none absolute -top-1 -right-1 h-4 min-w-4 rounded-full px-1 text-[10px] leading-none tabular-nums shadow-sm ring-1 ring-background"
            >
              {{ formatTabCount(tab.count) }}
            </Badge>
            <span
              v-if="tab.view === props.currentView"
              aria-hidden="true"
              class="absolute right-1 -bottom-px left-1 h-0.5 rounded bg-primary"
            />
          </button>
        </div>

        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <button
              type="button"
              role="tab"
              :aria-selected="isMoreView"
              :title="moreTriggerLabel"
              class="relative h-8 w-24 shrink-0 rounded-md text-center text-sm font-medium transition-colors"
              :class="
                isMoreView
                  ? 'text-foreground'
                  : 'text-muted-foreground hover:text-foreground'
              "
            >
              <span class="flex min-w-0 items-center justify-center gap-0.5">
                <span class="truncate">{{ moreTriggerLabel }}</span>
                <ChevronDown class="size-3.5 opacity-70" />
              </span>
              <Badge
                v-if="moreTriggerCount !== null && moreTriggerCount > 0"
                :variant="isMoreView ? 'default' : 'secondary'"
                class="pointer-events-none absolute -top-1 -right-1 h-4 min-w-4 rounded-full px-1 text-[10px] leading-none tabular-nums shadow-sm ring-1 ring-background"
              >
                {{ formatTabCount(moreTriggerCount) }}
              </Badge>
              <span
                v-if="isMoreView"
                aria-hidden="true"
                class="absolute right-0.5 -bottom-px left-0.5 h-0.5 rounded bg-primary"
              />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-26 !min-w-26">
            <DropdownMenuItem
              v-for="tab in moreTabs"
              :key="tab.view"
              class="flex items-center justify-between gap-2"
              :class="{
                'bg-muted text-foreground': tab.view === props.currentView,
              }"
              @select="selectView(tab.view)"
            >
              <span>{{ tab.label }}</span>
              <Badge
                v-if="tab.count !== null && tab.count > 0"
                variant="secondary"
                class="h-4 min-w-4 rounded-full px-1 text-[10px] leading-none tabular-nums"
              >
                {{ formatTabCount(tab.count) }}
              </Badge>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>
  </div>
</template>
