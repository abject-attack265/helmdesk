/**
 * 管理收件箱最近搜索、联系人范围、异步搜索结果和窄屏面板状态。
 */
import {
  readLocalStorageItem,
  removeLocalStorageItem,
  writeLocalStorageItem,
  type BrowserStorageContext,
} from '@/lib/browserStorage';
import inboxActions from '@/routes/app/inbox';
import type {
  InboxContactSearchResultData,
  InboxInstanceMessageSearchResultData,
  InboxSearchResultsData,
  InboxSelectionData,
} from '@/types/generated';
import axios from 'axios';
import {
  computed,
  nextTick,
  onMounted,
  onUnmounted,
  ref,
  toValue,
  watch,
  type ComputedRef,
  type MaybeRefOrGetter,
  type Ref,
} from 'vue';

const RECENT_SEARCH_LIMIT = 10;
const INBOX_SEARCH_MAX_LENGTH = 80;
const SEARCH_COMMIT_DEBOUNCE_MS = 300;
const DESKTOP_MEDIA_QUERY = '(min-width: 768px)';
const RECENT_SEARCH_STORAGE_KEY = 'helmdesk.inbox.recent_searches.system';

interface InboxSearchScopeContact {
  id: string;
  name: string;
}

export interface InboxMessageSearchTarget {
  threadId: string;
  messageId: string;
  isCurrentThreadSelected: boolean;
}

interface UseInboxSearchOptions {
  currentSearch: MaybeRefOrGetter<string | null>;
  currentThreadId: MaybeRefOrGetter<string | null>;
  requestedThreadId: MaybeRefOrGetter<string | null>;
  selection: MaybeRefOrGetter<InboxSelectionData | null>;
  formatContactName: (
    name: string | null | undefined,
    contactId: string,
  ) => string;
  onSearchRequested: (search: string) => void;
  onThreadSelectionRequested: (threadId: string) => void;
  onMessageSelectionRequested: (target: InboxMessageSearchTarget) => void;
  focusSearchInput?: () => void;
}

interface UseInboxSearchReturn {
  globalSearchActive: ComputedRef<boolean>;
  searchInputValue: Ref<string>;
  searchPanelActive: Ref<boolean>;
  searchScopeContact: Ref<InboxSearchScopeContact | null>;
  recentSearchKeywords: Ref<string[]>;
  globalContactSearchResults: Ref<InboxContactSearchResultData[]>;
  globalMessageSearchResults: Ref<InboxInstanceMessageSearchResultData[]>;
  globalSearchLoading: Ref<boolean>;
  globalSearchEmpty: ComputedRef<boolean>;
  globalSearchFailed: ComputedRef<boolean>;
  updateSearchInput: (value: string) => void;
  commitSearchInput: () => void;
  exitSearchPanel: () => void;
  onSearchPanelActiveChange: (value: boolean) => void;
  openConversationScopedSearch: () => void;
  removeSearchScope: () => void;
  clearRecentSearchKeywords: () => void;
  applyRecentSearchKeyword: (keyword: string) => void;
  openGlobalContactSearchResult: (result: InboxContactSearchResultData) => void;
  openGlobalMessageSearchResult: (
    result: InboxInstanceMessageSearchResultData,
  ) => void;
}

/** 按 InboxFiltersData 的地址栏约束规范化搜索词。 */
function normalizeInboxSearch(value: string): string {
  return Array.from(value.trim()).slice(0, INBOX_SEARCH_MAX_LENGTH).join('');
}

/** localStorage 内容不可信，只接纳去重后的非空字符串。 */
function normalizeRecentSearchKeywords(value: unknown[]): string[] {
  const keywords: string[] = [];
  for (const item of value) {
    if (typeof item !== 'string') {
      continue;
    }

    const keyword = normalizeInboxSearch(item);
    if (keyword === '' || keywords.includes(keyword)) {
      continue;
    }

    keywords.push(keyword);
    if (keywords.length === RECENT_SEARCH_LIMIT) {
      break;
    }
  }

  return keywords;
}

function isSearchCancellation(error: unknown): boolean {
  return (
    axios.isCancel(error) ||
    (error instanceof DOMException && error.name === 'AbortError')
  );
}

function isNullableString(value: unknown): value is string | null {
  return value === null || typeof value === 'string';
}

function isContactSearchResult(
  value: unknown,
): value is InboxContactSearchResultData {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return false;
  }

  const result = value as Record<string, unknown>;

  return (
    typeof result.id === 'string' &&
    isNullableString(result.name) &&
    isNullableString(result.avatar_url) &&
    typeof result.thread_id === 'string' &&
    isNullableString(result.last_message_preview) &&
    isNullableString(result.last_message_at)
  );
}

function isMessageSearchResult(
  value: unknown,
): value is InboxInstanceMessageSearchResultData {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    return false;
  }

  const result = value as Record<string, unknown>;

  return (
    typeof result.id === 'string' &&
    typeof result.thread_id === 'string' &&
    typeof result.contact_id === 'string' &&
    isNullableString(result.contact_name) &&
    isNullableString(result.contact_avatar_url) &&
    typeof result.role === 'string' &&
    typeof result.role_label === 'string' &&
    typeof result.kind === 'string' &&
    isNullableString(result.sender_name) &&
    isNullableString(result.content) &&
    typeof result.matched_content === 'string' &&
    typeof result.occurred_at === 'string'
  );
}

/** 校验搜索响应中的联系人和消息字段。 */
function parseSearchResults(payload: unknown): InboxSearchResultsData {
  if (
    typeof payload !== 'object' ||
    payload === null ||
    Array.isArray(payload)
  ) {
    throw new Error('收件箱搜索响应格式无效');
  }

  const result = payload as Record<string, unknown>;
  if (
    !Array.isArray(result.contacts) ||
    !result.contacts.every(isContactSearchResult) ||
    !Array.isArray(result.messages) ||
    !result.messages.every(isMessageSearchResult)
  ) {
    throw new Error('收件箱搜索响应格式无效');
  }

  return {
    contacts: result.contacts,
    messages: result.messages,
  };
}

/** 创建收件箱搜索状态并在输入条件变化时刷新远端结果。 */
export function useInboxSearch(
  options: UseInboxSearchOptions,
): UseInboxSearchReturn {
  const initialSearch = normalizeInboxSearch(
    toValue(options.currentSearch) ?? '',
  );
  const committedSearch = ref(initialSearch);
  const searchInputValue = ref(initialSearch);
  const globalSearchActive = computed(() => committedSearch.value !== '');
  const searchPanelActive = ref(globalSearchActive.value);
  const searchScopeContact = ref<InboxSearchScopeContact | null>(null);
  const recentSearchKeywords = ref<string[]>([]);
  const globalContactSearchResults = ref<InboxContactSearchResultData[]>([]);
  const globalMessageSearchResults = ref<
    InboxInstanceMessageSearchResultData[]
  >([]);
  const globalSearchLoading = ref(false);
  const globalSearchFailedState = ref(false);
  let globalSearchController: AbortController | null = null;
  let searchCommitTimer: number | null = null;
  let mounted = false;

  const globalSearchEmpty = computed(
    () =>
      !globalSearchLoading.value &&
      !globalSearchFailedState.value &&
      globalContactSearchResults.value.length === 0 &&
      globalMessageSearchResults.value.length === 0,
  );
  const globalSearchFailed = computed(
    () => !globalSearchLoading.value && globalSearchFailedState.value,
  );

  function recentSearchStorageContext(): BrowserStorageContext {
    return {
      channel: '[inbox-recent-searches]',
      details: { scope: 'system' },
    };
  }

  /** 读取最近搜索记录，并修复或清理存储里的无效内容。 */
  function loadRecentSearchKeywords(): string[] {
    const context = recentSearchStorageContext();
    const raw = readLocalStorageItem(RECENT_SEARCH_STORAGE_KEY, context);
    if (raw === null) {
      return [];
    }

    let parsed: unknown;
    try {
      parsed = JSON.parse(raw);
    } catch (parseError) {
      console.warn('[inbox-recent-searches] 最近搜索记录解析失败', {
        scope: 'system',
        storageKey: RECENT_SEARCH_STORAGE_KEY,
        errorType:
          parseError instanceof Error ? parseError.name : typeof parseError,
      });
      removeLocalStorageItem(RECENT_SEARCH_STORAGE_KEY, context);

      return [];
    }

    if (!Array.isArray(parsed)) {
      console.warn('[inbox-recent-searches] 最近搜索记录格式无效', {
        scope: 'system',
        storageKey: RECENT_SEARCH_STORAGE_KEY,
      });
      removeLocalStorageItem(RECENT_SEARCH_STORAGE_KEY, context);

      return [];
    }

    const keywords = normalizeRecentSearchKeywords(parsed);
    if (raw !== JSON.stringify(keywords)) {
      console.warn('[inbox-recent-searches] 最近搜索记录包含无效条目', {
        scope: 'system',
        storageKey: RECENT_SEARCH_STORAGE_KEY,
      });
      writeLocalStorageItem(
        RECENT_SEARCH_STORAGE_KEY,
        JSON.stringify(keywords),
        context,
      );
    }

    return keywords;
  }

  function persistRecentSearchKeywords(): void {
    writeLocalStorageItem(
      RECENT_SEARCH_STORAGE_KEY,
      JSON.stringify(recentSearchKeywords.value),
      recentSearchStorageContext(),
    );
  }

  function recordRecentSearchKeyword(value: string | null | undefined): void {
    const keyword = normalizeInboxSearch(value ?? '');
    if (keyword === '') {
      return;
    }

    recentSearchKeywords.value = [
      keyword,
      ...recentSearchKeywords.value.filter((item) => item !== keyword),
    ].slice(0, RECENT_SEARCH_LIMIT);
    persistRecentSearchKeywords();
  }

  function clearRecentSearchKeywords(): void {
    recentSearchKeywords.value = [];
    persistRecentSearchKeywords();
  }

  function clearSearchCommitTimer(): void {
    if (searchCommitTimer === null) {
      return;
    }

    window.clearTimeout(searchCommitTimer);
    searchCommitTimer = null;
  }

  /** 提交规范化搜索词，并同步 URL 所属的页面状态。 */
  function commitSearch(value: string): string {
    clearSearchCommitTimer();
    const search = normalizeInboxSearch(value);
    committedSearch.value = search;
    searchInputValue.value = search;

    const currentSearch = normalizeInboxSearch(
      toValue(options.currentSearch) ?? '',
    );
    if (search !== currentSearch) {
      options.onSearchRequested(search);
    }

    return search;
  }

  function updateSearchInput(value: string): void {
    searchInputValue.value = value;
    clearSearchCommitTimer();
    searchCommitTimer = window.setTimeout(() => {
      searchCommitTimer = null;
      commitSearch(searchInputValue.value);
    }, SEARCH_COMMIT_DEBOUNCE_MS);
  }

  function commitSearchInput(): void {
    const search = commitSearch(searchInputValue.value);
    recordRecentSearchKeyword(search);
  }

  function exitSearchPanel(): void {
    commitSearch('');
    searchScopeContact.value = null;
    searchPanelActive.value = false;
  }

  function applyRecentSearchKeyword(keyword: string): void {
    const normalizedKeyword = normalizeInboxSearch(keyword);
    if (normalizedKeyword === '') {
      return;
    }

    recordRecentSearchKeyword(normalizedKeyword);
    commitSearch(normalizedKeyword);
  }

  function onSearchPanelActiveChange(value: boolean): void {
    searchPanelActive.value = value;
    if (!value) {
      searchScopeContact.value = null;
    }
  }

  function removeSearchScope(): void {
    commitSearch(searchInputValue.value);
    searchScopeContact.value = null;
  }

  function openConversationScopedSearch(): void {
    const contact = toValue(options.selection)?.contact;
    if (!contact) {
      throw new Error('当前没有可搜索的联系人');
    }

    searchScopeContact.value = {
      id: contact.id,
      name: options.formatContactName(contact.name, contact.id),
    };
    searchPanelActive.value = true;
    void nextTick(() => options.focusSearchInput?.());
  }

  function clearGlobalSearchResults(): void {
    globalContactSearchResults.value = [];
    globalMessageSearchResults.value = [];
  }

  function abortGlobalSearch(): void {
    globalSearchController?.abort();
    globalSearchController = null;
  }

  /** 只接纳当前请求控制器返回的搜索结果。 */
  async function executeGlobalSearch(
    query: string,
    contactId: string | null,
  ): Promise<void> {
    const controller = new AbortController();
    globalSearchController = controller;
    clearGlobalSearchResults();
    globalSearchLoading.value = true;
    globalSearchFailedState.value = false;

    try {
      const response = await axios.get<unknown>(
        inboxActions.search.url({
          query: contactId
            ? { search: query, contact_id: contactId }
            : { search: query },
        }),
        { signal: controller.signal },
      );

      if (controller.signal.aborted || globalSearchController !== controller) {
        return;
      }

      const data = parseSearchResults(response.data);
      globalContactSearchResults.value = data.contacts;
      globalMessageSearchResults.value = data.messages;
    } catch (error) {
      if (
        isSearchCancellation(error) ||
        controller.signal.aborted ||
        globalSearchController !== controller
      ) {
        return;
      }

      globalSearchFailedState.value = true;
      console.warn('[inbox-search] 全局搜索请求失败', {
        scope: 'system',
        contactScoped: contactId !== null,
        errorType: axios.isAxiosError(error)
          ? 'AxiosError'
          : error instanceof Error
            ? error.name
            : typeof error,
        errorCode: axios.isAxiosError(error) ? error.code : undefined,
        status: axios.isAxiosError(error) ? error.response?.status : undefined,
      });
      clearGlobalSearchResults();
    } finally {
      if (globalSearchController === controller) {
        globalSearchController = null;
        globalSearchLoading.value = false;
      }
    }
  }

  function isMobileViewport(): boolean {
    return !window.matchMedia(DESKTOP_MEDIA_QUERY).matches;
  }

  function collapseSearchPanelOnMobile(): void {
    if (isMobileViewport()) {
      searchPanelActive.value = false;
    }
  }

  /** 丢弃尚未提交的输入，防止结果导航后被防抖任务覆盖。 */
  function discardSearchInputDraft(): void {
    clearSearchCommitTimer();
    searchInputValue.value = committedSearch.value;
  }

  function openGlobalContactSearchResult(
    result: InboxContactSearchResultData,
  ): void {
    discardSearchInputDraft();
    recordRecentSearchKeyword(committedSearch.value);
    collapseSearchPanelOnMobile();

    if (toValue(options.requestedThreadId) === result.thread_id) {
      return;
    }

    options.onThreadSelectionRequested(result.thread_id);
  }

  function openGlobalMessageSearchResult(
    result: InboxInstanceMessageSearchResultData,
  ): void {
    discardSearchInputDraft();
    recordRecentSearchKeyword(committedSearch.value);
    collapseSearchPanelOnMobile();

    const isCurrentThreadSelected =
      toValue(options.currentThreadId) === result.thread_id &&
      toValue(options.selection) !== null;

    options.onMessageSelectionRequested({
      threadId: result.thread_id,
      messageId: result.id,
      isCurrentThreadSelected,
    });
  }

  watch(
    () => toValue(options.currentSearch),
    (value) => {
      const search = normalizeInboxSearch(value ?? '');
      committedSearch.value = search;
      if (searchCommitTimer === null) {
        searchInputValue.value = search;
      }
    },
    { flush: 'sync' },
  );

  /** SSR 期间只同步加载状态，挂载后再请求远端结果。 */
  function refreshGlobalSearch(): void {
    abortGlobalSearch();

    const query = committedSearch.value;
    if (!mounted) {
      clearGlobalSearchResults();
      globalSearchLoading.value = query !== '';
      globalSearchFailedState.value = false;
      return;
    }

    if (query === '') {
      clearGlobalSearchResults();
      globalSearchLoading.value = false;
      globalSearchFailedState.value = false;
      return;
    }

    void executeGlobalSearch(query, searchScopeContact.value?.id ?? null);
  }

  watch(
    [committedSearch, () => searchScopeContact.value?.id],
    refreshGlobalSearch,
    { immediate: true },
  );

  onMounted(() => {
    mounted = true;
    recentSearchKeywords.value = loadRecentSearchKeywords();
    refreshGlobalSearch();
  });

  onUnmounted(() => {
    mounted = false;
    clearSearchCommitTimer();
    abortGlobalSearch();
  });

  return {
    globalSearchActive,
    searchInputValue,
    searchPanelActive,
    searchScopeContact,
    recentSearchKeywords,
    globalContactSearchResults,
    globalMessageSearchResults,
    globalSearchLoading,
    globalSearchEmpty,
    globalSearchFailed,
    updateSearchInput,
    commitSearchInput,
    exitSearchPanel,
    onSearchPanelActiveChange,
    openConversationScopedSearch,
    removeSearchScope,
    clearRecentSearchKeywords,
    applyRecentSearchKeyword,
    openGlobalContactSearchResult,
    openGlobalMessageSearchResult,
  };
}
