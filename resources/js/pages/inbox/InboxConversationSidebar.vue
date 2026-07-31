<!--
  收件箱左侧栏消费 ShowInboxPagePropsData 拆出的筛选、搜索和列表 Data，
  展示搜索结果与可滚动的会话列表。
-->
<script setup lang="ts">
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useI18n } from '@/composables/useI18n';
import InboxConversationListItem from '@/pages/inbox/InboxConversationListItem.vue';
import InboxGlobalContactSearchList from '@/pages/inbox/InboxGlobalContactSearchList.vue';
import InboxGlobalMessageSearchList from '@/pages/inbox/InboxGlobalMessageSearchList.vue';
import InboxToolbar from '@/pages/inbox/InboxToolbar.vue';
import type { InboxNavigationOverrides } from '@/pages/inbox/inboxNavigation';
import type {
  EnabledWebChannelData,
  InboxContactSearchResultData,
  InboxInstanceMessageSearchResultData,
  InboxTabCountsData,
  InboxView,
  ListConversationItemData,
  UserOptionData,
} from '@/types/generated';
import { Search } from '@lucide/vue';
import { ref } from 'vue';

interface InboxSearchScopeContact {
  id: string;
  name: string;
}

interface Props {
  hasSelection: boolean;
  currentView: InboxView;
  currentChannelId: string | null;
  currentAssignee: string | null;
  currentSearch: string | null;
  searchInputValue: string;
  currentImportantOnly: boolean;
  currentThreadId: string | null;
  enabledWebChannels: EnabledWebChannelData[];
  teammates: UserOptionData[];
  tabCounts: InboxTabCountsData;
  searchPanelActive: boolean;
  searchScopeContact: InboxSearchScopeContact | null;
  recentSearchKeywords: string[];
  globalSearchActive: boolean;
  globalSearchLoading: boolean;
  globalSearchFailed: boolean;
  globalSearchEmpty: boolean;
  globalContactSearchResults: InboxContactSearchResultData[];
  globalMessageSearchResults: InboxInstanceMessageSearchResultData[];
  conversationList: ListConversationItemData[];
  loadingMoreConversations: boolean;
  conversationPreview: (conversation: ListConversationItemData) => string;
  conversationUnreadCount: (conversation: ListConversationItemData) => number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  (event: 'update:searchPanelActive', value: boolean): void;
  (event: 'removeSearchScope'): void;
  (event: 'clearRecentSearchKeywords'): void;
  (event: 'applyRecentSearchKeyword', keyword: string): void;
  (event: 'searchInput', search: string): void;
  (event: 'searchEnter'): void;
  (event: 'searchExit'): void;
  (event: 'navigate', overrides: InboxNavigationOverrides): void;
  (
    event: 'selectContactSearchResult',
    result: InboxContactSearchResultData,
  ): void;
  (
    event: 'selectMessageSearchResult',
    result: InboxInstanceMessageSearchResultData,
  ): void;
  (event: 'selectConversation', conversation: ListConversationItemData): void;
  (event: 'scroll', element: HTMLElement): void;
}>();

const { t } = useI18n();
const inboxToolbarRef = ref<InstanceType<typeof InboxToolbar> | null>(null);
const conversationListScrollRef = ref<HTMLElement | null>(null);

function focusSearchInput(): void {
  inboxToolbarRef.value?.focusSearchInput();
}

function scrollConversationListToTop(): void {
  if (conversationListScrollRef.value) {
    conversationListScrollRef.value.scrollTop = 0;
  }
}

function handleConversationListScroll(event: Event): void {
  if (!(event.currentTarget instanceof HTMLElement)) {
    throw new Error('收件箱会话列表滚动容器缺失');
  }

  emit('scroll', event.currentTarget);
}

defineExpose({
  focusSearchInput,
  scrollConversationListToTop,
});
</script>

<template>
  <section
    class="min-h-0 w-full shrink-0 flex-col border-r md:w-78"
    :class="
      props.hasSelection && !props.searchPanelActive ? 'hidden md:flex' : 'flex'
    "
  >
    <div class="flex shrink-0 items-center gap-2 border-b px-2 py-2 md:hidden">
      <SidebarTrigger class="-ml-1" />
      <span class="text-sm font-semibold">{{ t('收件箱') }}</span>
    </div>

    <InboxToolbar
      ref="inboxToolbarRef"
      :current-view="props.currentView"
      :current-channel-id="props.currentChannelId"
      :current-assignee="props.currentAssignee"
      :search-value="props.searchInputValue"
      :current-important-only="props.currentImportantOnly"
      :enabled-web-channels="props.enabledWebChannels"
      :teammates="props.teammates"
      :tab-counts="props.tabCounts"
      :search-panel-active="props.searchPanelActive"
      :scope-contact="props.searchScopeContact"
      @update:search-panel-active="emit('update:searchPanelActive', $event)"
      @remove-scope="emit('removeSearchScope')"
      @search-input="emit('searchInput', $event)"
      @search-enter="emit('searchEnter')"
      @search-exit="emit('searchExit')"
      @navigate="emit('navigate', $event)"
    />

    <div
      v-if="props.searchPanelActive && !props.globalSearchActive"
      class="min-h-0 flex-1 overflow-y-auto"
    >
      <template v-if="props.recentSearchKeywords.length > 0">
        <div
          class="flex items-center justify-between border-b bg-muted/80 px-3 py-1.5 text-xs text-muted-foreground"
        >
          <span>{{ t('最近搜索') }}</span>
          <button
            type="button"
            class="hover:text-foreground"
            @click="emit('clearRecentSearchKeywords')"
          >
            {{ t('清除') }}
          </button>
        </div>
        <div class="divide-y">
          <button
            v-for="keyword in props.recentSearchKeywords"
            :key="keyword"
            type="button"
            class="flex w-full cursor-pointer items-center gap-2.5 px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted/50"
            @click="emit('applyRecentSearchKeyword', keyword)"
          >
            <Search class="size-3.5 shrink-0 text-muted-foreground" />
            <span class="min-w-0 truncate">{{ keyword }}</span>
          </button>
        </div>
      </template>
      <div v-else class="p-6 text-center text-sm text-muted-foreground">
        {{ t('输入联系人或消息内容') }}
      </div>
    </div>

    <div
      v-else-if="props.globalSearchActive"
      class="min-h-0 flex-1 overflow-y-auto"
    >
      <div
        v-if="props.globalSearchLoading"
        class="border-y bg-muted/80 px-3 py-1.5 text-xs text-muted-foreground"
      >
        {{ t('搜索中...') }}
      </div>
      <div
        v-else-if="props.globalSearchFailed"
        class="p-6 text-center text-sm text-destructive"
      >
        {{ t('搜索失败，请稍后重试') }}
      </div>
      <div
        v-else-if="props.globalSearchEmpty"
        class="p-6 text-center text-sm text-muted-foreground"
      >
        {{ t('没有找到相关联系人或消息') }}
      </div>
      <InboxGlobalContactSearchList
        v-if="props.globalContactSearchResults.length > 0"
        :results="props.globalContactSearchResults"
        :search="props.currentSearch ?? ''"
        @select="emit('selectContactSearchResult', $event)"
      />
      <InboxGlobalMessageSearchList
        v-if="props.globalMessageSearchResults.length > 0"
        :results="props.globalMessageSearchResults"
        :search="props.currentSearch ?? ''"
        @select="emit('selectMessageSearchResult', $event)"
      />
    </div>

    <div
      v-else-if="props.conversationList.length === 0"
      class="p-6 text-center text-sm text-muted-foreground"
    >
      {{ t('暂无会话') }}
    </div>

    <div
      v-else
      ref="conversationListScrollRef"
      class="min-h-0 flex-1 overflow-y-auto"
      @scroll.passive="handleConversationListScroll"
    >
      <div class="divide-y">
        <InboxConversationListItem
          v-for="conversation in props.conversationList"
          :key="conversation.thread_id"
          :conversation="conversation"
          :active="props.currentThreadId === conversation.thread_id"
          :preview="props.conversationPreview(conversation)"
          :unread-count="props.conversationUnreadCount(conversation)"
          @select="emit('selectConversation', $event)"
        />
      </div>
      <div
        v-if="props.loadingMoreConversations"
        class="flex items-center justify-center py-3 text-xs text-muted-foreground"
      >
        {{ t('加载中...') }}
      </div>
    </div>
  </section>
</template>
