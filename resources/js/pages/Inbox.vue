<!--
  收件箱使用 ShowInboxPagePropsData 管理会话列表、消息记录、回复和联系人资料。
-->
<script setup lang="ts">
import ImagePreviewDialog from '@/components/common/ImagePreviewDialog.vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/useI18n';
import { useInboxAutoTranslate } from '@/composables/useInboxAutoTranslate';
import { useInboxConversationCommands } from '@/composables/useInboxConversationCommands';
import { useInboxConversationPreviewAutoTranslate } from '@/composables/useInboxConversationPreviewAutoTranslate';
import { useInboxRealtimeRefresh } from '@/composables/useInboxRealtimeRefresh';
import { useInboxReplyAttachments } from '@/composables/useInboxReplyAttachments';
import { useInboxReplyPolish } from '@/composables/useInboxReplyPolish';
import { useInboxReplyQuote } from '@/composables/useInboxReplyQuote';
import { useInboxRequestCoordinator } from '@/composables/useInboxRequestCoordinator';
import {
  type InboxMessageSearchTarget,
  useInboxSearch,
} from '@/composables/useInboxSearch';
import { useInboxSummaryAutoTranslate } from '@/composables/useInboxSummaryAutoTranslate';
import { useInboxTimelineWindow } from '@/composables/useInboxTimelineWindow';
import { useInboxTranslationPreferences } from '@/composables/useInboxTranslationPreferences';
import { useReceptionActivityState } from '@/composables/useReceptionActivityState';
import { useReplyTranslationPreview } from '@/composables/useReplyTranslationPreview';
import { useToast } from '@/composables/useToast';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import { appContentLayout } from '@/layouts/pageLayouts';
import { COMPOSER_EMOJIS } from '@/lib/composerEmojis';
import { subscribeReceptionActivity } from '@/lib/mercure';
import CannedReplyPicker from '@/pages/inbox/CannedReplyPicker.vue';
import ConversationSummaryBlock from '@/pages/inbox/ConversationSummaryBlock.vue';
import InboxConversationHeader from '@/pages/inbox/InboxConversationHeader.vue';
import InboxConversationSidebar from '@/pages/inbox/InboxConversationSidebar.vue';
import InboxContextPane from '@/pages/inbox/InboxContextPane.vue';
import InboxPendingReplyUploads from '@/pages/inbox/InboxPendingReplyUploads.vue';
import InboxReplyPolishPopover from '@/pages/inbox/InboxReplyPolishPopover.vue';
import InboxStitchedTimeline from '@/pages/inbox/InboxStitchedTimeline.vue';
import {
  type InboxNavigationOverrides,
  buildInboxUrl as createInboxUrl,
  inboxNavigationStateFromPageProps,
  mergeInboxNavigationState,
} from '@/pages/inbox/inboxNavigation';
import inboxActions from '@/routes/app/inbox';
import type { AppPageProps } from '@/types';
import type {
  ConversationInboxStatus,
  ConversationStatus,
  ConversationSummaryData,
  FormRenewInboxConversationActivityData,
  FormReplyInboxConversationData,
  ListConversationItemData,
  ShowInboxPagePropsData,
} from '@/types/generated';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
  Image as ImageIcon,
  Languages,
  MessageSquareQuote,
  Paperclip,
  Smile,
  X,
} from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

defineOptions({ layout: appContentLayout });

const props = defineProps<ShowInboxPagePropsData>();

const { t } = useI18n();
const { toast } = useToast();
const { formatVisitorName } = useVisitorDisplay();
const page = usePage<AppPageProps>();
const currentUserId = computed(() => page.props.auth.user.id);
const currentUserLocale = computed(() => page.props.auth.user.locale);
const selectionComputed = computed(() => props.selection);
const receptionLanguageOptions = computed(
  () => props.reception_language_options,
);
const {
  showTimelineEvents,
  autoTranslateVisibleMessages,
  autoTranslateReply,
  translationSourceLocale,
  translationTargetLocale,
  translationPopoverOpen,
  translationEnabled,
  translationConversationScopeId,
  translationSourceOptions,
  replyAutoTranslateToggleTitle,
  toggleTimelineEvents,
  toggleReplyAutoTranslate,
  translateCurrentConversation,
} = useInboxTranslationPreferences({
  currentUserLocale,
  receptionLanguageOptions,
  selection: selectionComputed,
});
const inboxNavigationState = ref(inboxNavigationStateFromPageProps(props));
const contextWritePending = ref(false);
const replyAttachmentTransferPending = ref(false);
const conversationListRefreshPending = ref(false);
const loadingMoreConversations = ref(false);
const lastConversationListScrollTop = ref(0);
const CONVERSATION_LIST_TOP_THRESHOLD_PX = 80;
const CONVERSATION_LIST_LOAD_MORE_THRESHOLD_PX = 200;

const {
  foregroundInteractionBlocked: inboxForegroundInteractionBlocked,
  interactionBlocked: inboxInteractionBlocked,
  runNavigation: runInboxNavigation,
  prepareNavigation: prepareInboxNavigation,
  finishNavigation: finishInboxNavigation,
  cancelWaitingNavigation: cancelWaitingInboxNavigation,
  hasWaitingNavigation: hasWaitingInboxNavigation,
  navigationInFlight: inboxNavigationInFlight,
  backgroundRequestBlocked: inboxBackgroundRequestBlocked,
  beginRefresh: beginInboxRefresh,
  trackRefresh: trackInboxRefresh,
  finishRefresh: finishInboxRefresh,
  cancelRefresh: cancelInboxRefresh,
  deferReload: deferInboxReload,
  requestSearchStateWrite: requestInboxSearchStateWrite,
  deferredSearch: deferredInboxSearch,
} = useInboxRequestCoordinator({
  conversationId: () => props.selection?.conversation.id ?? null,
  contextWritePending,
  attachmentTransferPending: replyAttachmentTransferPending,
  syncNavigationStateFromProps: () => syncInboxNavigationStateFromProps(),
  cancelAttachmentFlows: () => cancelReplyAttachmentFlows(),
  reloadListAndCounts: () => reloadInboxListAndCounts(),
  reloadWithSelection: () => reloadInboxWithSelection(),
  writeSearchState: (search, onFinish) =>
    writeInboxSearchState(search, onFinish),
});

/** 用服务端 PageProps 同步导航状态，并保留等待写入的搜索词。 */
function syncInboxNavigationStateFromProps(): void {
  if (hasWaitingInboxNavigation()) {
    return;
  }

  const state = inboxNavigationStateFromPageProps(props);
  const pendingSearch = deferredInboxSearch();
  if (pendingSearch !== undefined) {
    state.filters.search = pendingSearch || null;
  }
  inboxNavigationState.value = state;
}

watch(
  () =>
    [
      props.current_view,
      props.current_channel_id,
      props.current_assignee,
      props.current_search,
      props.current_important_only,
      props.current_thread_id,
      props.current_pane,
    ] as const,
  syncInboxNavigationStateFromProps,
  { flush: 'sync' },
);

/** 回复发送失败时取消等待中的显式导航，保留回复草稿供用户处理。 */
function handleReplyWriteFailed(): void {
  if (cancelWaitingInboxNavigation()) {
    console.info('[inbox-reply] 回复发送失败，取消等待中的导航', {
      scope: 'system',
      conversationId: props.selection?.conversation.id ?? null,
    });
  }
}

/** 联系人资料保存失败时取消等待中的显式导航，保留错误字段供用户修正。 */
function handleContextWriteFailed(): void {
  if (cancelWaitingInboxNavigation()) {
    console.info('[inbox-context] 联系人资料保存失败，取消等待中的导航', {
      conversationId: props.selection?.conversation.id ?? null,
    });
  }
}

function inboxUrl(overrides: InboxNavigationOverrides = {}): string {
  return createInboxUrl(inboxNavigationState.value, overrides);
}

/** 使用 Inertia 客户端访问同步搜索词与地址，不发起页面请求。 */
function writeInboxSearchState(search: string, onFinish: () => void): void {
  router.replace<AppPageProps<ShowInboxPagePropsData>>({
    url: inboxUrl(),
    props: (currentProps) => ({
      ...currentProps,
      current_search: search || null,
    }),
    flash: (currentFlash) => currentFlash,
    preserveScroll: true,
    preserveState: true,
    onFinish,
  });
}

const currentUserContext = computed(() => page.props.currentUserContext);
const isCurrentUserOffline = computed(
  () => Number(currentUserContext.value?.user_online_status?.value) === 0,
);
const replyPolishToneOptions = computed(() => props.reply_polish_tone_options);
const replyAssistantModeOptions = computed(
  () => props.reply_assistant_mode_options,
);

const inboxContextPaneRef = ref<InstanceType<typeof InboxContextPane> | null>(
  null,
);
const inboxConversationSidebarRef = ref<InstanceType<
  typeof InboxConversationSidebar
> | null>(null);
const replyComposerRef = ref<HTMLTextAreaElement | null>(null);
const replyFileInputRef = ref<HTMLInputElement | null>(null);
const replyImageInputRef = ref<HTMLInputElement | null>(null);

function conversationListPreview(
  conversation: ListConversationItemData,
): string {
  if (autoTranslateVisibleMessages.value) {
    const translated =
      conversation.last_message_translation_previews[
        translationTargetLocale.value
      ];
    if (translated) {
      return translated;
    }
  }

  return conversation.last_message_preview || t('暂无消息');
}

function requestInboxSearch(search: string): void {
  const normalizedSearch = search.trim();
  inboxNavigationState.value = mergeInboxNavigationState(
    inboxNavigationState.value,
    {
      filters: { search: normalizedSearch || null },
    },
  );
  requestInboxSearchStateWrite(normalizedSearch);
}

/** 执行收件箱导航，并在访问结束后释放刷新锁。 */
function navigateInbox(overrides: InboxNavigationOverrides): void {
  inboxNavigationState.value = mergeInboxNavigationState(
    inboxNavigationState.value,
    overrides,
  );
  runInboxNavigation(() => {
    prepareInboxNavigation();
    router.get(
      inboxUrl(),
      {},
      {
        preserveScroll: true,
        preserveState: true,
        only: [
          'current_view',
          'current_channel_id',
          'current_assignee',
          'current_search',
          'current_important_only',
          'current_thread_id',
          'current_pane',
          'conversation_list',
          'conversation_list_next_cursor',
          'selection',
          'tab_counts',
        ],
        reset: ['conversation_list'],
        onFinish: finishInboxNavigation,
      },
    );
  });
}

/** 切换选中线程或返回列表时保持会话列表与滚动位置。 */
function navigateInboxSelection(
  overrides: InboxNavigationOverrides,
  onSuccess?: () => void,
): void {
  inboxNavigationState.value = mergeInboxNavigationState(
    inboxNavigationState.value,
    overrides,
  );
  runInboxNavigation(() => {
    prepareInboxNavigation();
    router.get(
      inboxUrl(),
      {},
      {
        only: ['selection', 'current_thread_id', 'current_pane', 'tab_counts'],
        preserveScroll: true,
        preserveState: true,
        onSuccess,
        onFinish: finishInboxNavigation,
      },
    );
  });
}

function requestSearchThreadSelection(threadId: string): void {
  navigateInboxSelection({ threadId, pane: null });
}

function requestSearchMessageSelection(target: InboxMessageSearchTarget): void {
  if (target.isCurrentThreadSelected) {
    void anchorTimelineToMessage(target.messageId);

    return;
  }

  navigateInboxSelection({ threadId: target.threadId, pane: null }, () => {
    void nextTick(() => {
      if (props.current_thread_id !== target.threadId) {
        return;
      }

      void anchorTimelineToMessage(target.messageId);
    });
  });
}

const {
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
} = useInboxSearch({
  currentSearch: () => inboxNavigationState.value.filters.search,
  currentThreadId: () => props.current_thread_id,
  requestedThreadId: () => inboxNavigationState.value.threadId,
  selection: selectionComputed,
  formatContactName: formatVisitorName,
  onSearchRequested: requestInboxSearch,
  onThreadSelectionRequested: requestSearchThreadSelection,
  onMessageSelectionRequested: requestSearchMessageSelection,
  focusSearchInput: () => inboxConversationSidebarRef.value?.focusSearchInput(),
});

const emojiPopoverOpen = ref(false);
const cannedReplyPickerOpen = ref(false);
const cannedReplyPickerQuery = ref('');
// 记录行首快捷指令的范围，选中快捷回复后用正文替换。
const cannedReplyTriggerRange = ref<{ start: number; end: number } | null>(
  null,
);
const cannedReplyPickerRef = ref<InstanceType<typeof CannedReplyPicker> | null>(
  null,
);
const locallyReadConversationIds = ref<Set<string>>(new Set());
const markingReadRequests = new Map<string, Promise<boolean>>();

/** 判断会话列表是否位于允许实时重排的顶部区域。 */
function conversationListNearTop(): boolean {
  return (
    lastConversationListScrollTop.value <= CONVERSATION_LIST_TOP_THRESHOLD_PX
  );
}

/** 标记会话列表需要在返回顶部后刷新。 */
function deferConversationListRefresh(): void {
  if (conversationListRefreshPending.value) {
    return;
  }

  conversationListRefreshPending.value = true;
  console.info('[inbox-list] 深度滚动期间延迟会话列表刷新', {
    scrollTop: lastConversationListScrollTop.value,
  });
}

/** 记录静默后台刷新返回的字段错误。 */
function warnInboxRefreshFailed(
  scope: 'list' | 'selection' | 'load-more',
  errors: Record<string, string>,
): void {
  console.warn('[inbox-refresh] 收件箱后台刷新失败', {
    scope,
    errorFields: Object.keys(errors),
  });
}

/** 在顶部刷新第一页；深度滚动时保持列表顺序并仅刷新计数。 */
function reloadInboxListAndCounts(): void {
  if (inboxBackgroundRequestBlocked()) {
    deferInboxReload('list');
    return;
  }

  // 计数刷新等待正在追加的游标页，避免取消用户触发的翻页。
  if (!conversationListNearTop() && loadingMoreConversations.value) {
    deferConversationListRefresh();
    deferInboxReload('list');
    return;
  }

  const refreshId = beginInboxRefresh('list');
  if (refreshId === null) {
    return;
  }

  if (!conversationListNearTop()) {
    deferConversationListRefresh();
    router.reload({
      only: ['tab_counts'],
      preserveUrl: true,
      onCancelToken: (token) => trackInboxRefresh(refreshId, token),
      onError: (errors) => warnInboxRefreshFailed('list', errors),
      onFinish: () => finishInboxRefresh(refreshId),
    });
    return;
  }

  router.reload({
    only: ['conversation_list', 'conversation_list_next_cursor', 'tab_counts'],
    reset: ['conversation_list'],
    preserveUrl: true,
    onCancelToken: (token) => trackInboxRefresh(refreshId, token),
    onError: (errors) => warnInboxRefreshFailed('list', errors),
    onSuccess: () => {
      conversationListRefreshPending.value = false;
    },
    onFinish: () => finishInboxRefresh(refreshId),
  });
}

/** 刷新当前选择；仅在列表顶部同步刷新第一页。 */
function reloadInboxWithSelection(): void {
  if (inboxBackgroundRequestBlocked()) {
    deferInboxReload('selection');
    return;
  }

  const data: Record<string, string> = {};
  if (props.current_thread_id) {
    data.thread_id = props.current_thread_id;
  }

  const refreshId = beginInboxRefresh('selection');
  if (refreshId === null) {
    return;
  }

  if (!conversationListNearTop()) {
    deferConversationListRefresh();
    router.reload({
      only: ['selection', 'current_thread_id', 'tab_counts'],
      data,
      preserveUrl: true,
      onCancelToken: (token) => trackInboxRefresh(refreshId, token),
      onError: (errors) => warnInboxRefreshFailed('selection', errors),
      onFinish: () => finishInboxRefresh(refreshId),
    });
    return;
  }

  router.reload({
    only: [
      'conversation_list',
      'conversation_list_next_cursor',
      'selection',
      'current_thread_id',
      'tab_counts',
    ],
    reset: ['conversation_list'],
    data,
    preserveUrl: true,
    onCancelToken: (token) => trackInboxRefresh(refreshId, token),
    onError: (errors) => warnInboxRefreshFailed('selection', errors),
    onSuccess: () => {
      conversationListRefreshPending.value = false;
    },
    onFinish: () => finishInboxRefresh(refreshId),
  });
}

/** 回复后仅在线程归属当前客服时切换到“我负责的”视图。 */
function switchToMineViewAfterReply(): void {
  const conversation = props.selection?.conversation;
  if (
    !conversation ||
    conversation.assigned_user_id !== currentUserId.value ||
    inboxNavigationState.value.filters.view === 'mine'
  ) {
    return;
  }

  inboxNavigationState.value = mergeInboxNavigationState(
    inboxNavigationState.value,
    { filters: { view: 'mine' } },
  );
  runInboxNavigation(() => {
    prepareInboxNavigation();
    router.get(
      inboxUrl(),
      {},
      {
        preserveScroll: true,
        preserveState: true,
        only: [
          'current_view',
          'conversation_list',
          'conversation_list_next_cursor',
          'selection',
          'tab_counts',
        ],
        reset: ['conversation_list'],
        onFinish: finishInboxNavigation,
      },
    );
  });
}

function scheduleMineViewAfterReply(conversationId: string): void {
  if (inboxNavigationInFlight()) {
    return;
  }

  void nextTick(() => {
    if (
      inboxNavigationInFlight() ||
      props.selection?.conversation.id !== conversationId
    ) {
      return;
    }

    switchToMineViewAfterReply();
  });
}

/** 会话列表向下滚动时按游标追加。 */
function loadMoreConversations(): void {
  const cursor = props.conversation_list_next_cursor;
  if (
    cursor === null ||
    loadingMoreConversations.value ||
    inboxBackgroundRequestBlocked()
  ) {
    return;
  }

  const refreshId = beginInboxRefresh('load-more');
  if (refreshId === null) {
    return;
  }

  loadingMoreConversations.value = true;
  router.reload({
    only: ['conversation_list', 'conversation_list_next_cursor'],
    data: { cursor },
    // 游标仅用于本次翻页，不写入地址栏。
    preserveUrl: true,
    onCancelToken: (token) => trackInboxRefresh(refreshId, token),
    onError: (errors) => warnInboxRefreshFailed('load-more', errors),
    onFinish: () => {
      loadingMoreConversations.value = false;
      finishInboxRefresh(refreshId);
    },
  });
}

function handleConversationListScroll(element: HTMLElement): void {
  const movedDown = element.scrollTop > lastConversationListScrollTop.value;
  lastConversationListScrollTop.value = element.scrollTop;

  if (conversationListNearTop() && conversationListRefreshPending.value) {
    reloadInboxListAndCounts();
    return;
  }

  if (!movedDown) {
    return;
  }

  const distanceToBottom =
    element.scrollHeight - element.scrollTop - element.clientHeight;
  if (distanceToBottom <= CONVERSATION_LIST_LOAD_MORE_THRESHOLD_PX) {
    loadMoreConversations();
  }
}

/** 列表上下文变化时取消刷新并复位滚动状态。 */
watch(
  () => [
    props.current_view,
    props.current_channel_id,
    props.current_assignee,
    props.current_important_only,
  ],
  () => {
    cancelInboxRefresh();
    conversationListRefreshPending.value = false;
    lastConversationListScrollTop.value = 0;
    loadingMoreConversations.value = false;
    void nextTick(() => {
      inboxConversationSidebarRef.value?.scrollConversationListToTop();
    });
  },
);

/** 选中会话变化及首次渲染时同步已读状态。 */
watch(
  () => props.selection?.conversation.id,
  () => {
    void markCurrentConversationRead();
  },
  { immediate: true },
);

function displayedUnreadCount(conversation: ListConversationItemData): number {
  return locallyReadConversationIds.value.has(conversation.id)
    ? 0
    : conversation.unread_count;
}

function rememberConversationRead(conversationId: string): void {
  locallyReadConversationIds.value = new Set([
    ...locallyReadConversationIds.value,
    conversationId,
  ]);
}

function forgetConversationRead(conversationId: string): void {
  if (!locallyReadConversationIds.value.has(conversationId)) {
    return;
  }

  const readConversationIds = new Set(locallyReadConversationIds.value);
  readConversationIds.delete(conversationId);
  locallyReadConversationIds.value = readConversationIds;
}

watch(
  () =>
    props.conversation_list.map(
      (conversation) => [conversation.id, conversation.unread_count] as const,
    ),
  (conversations) => {
    const visibleConversations = new Map(conversations);
    const readConversationIds = new Set(locallyReadConversationIds.value);

    for (const conversationId of readConversationIds) {
      const unreadCount = visibleConversations.get(conversationId);
      const requestKey = conversationId;
      if (
        unreadCount === undefined ||
        unreadCount === 0 ||
        !markingReadRequests.has(requestKey)
      ) {
        readConversationIds.delete(conversationId);
      }
    }

    if (readConversationIds.size !== locallyReadConversationIds.value.size) {
      locallyReadConversationIds.value = readConversationIds;
    }
  },
);

async function markConversationRead(
  conversationId: string,
  options: { reload?: boolean; ensureAfterPending?: boolean } = {},
): Promise<void> {
  const requestKey = conversationId;
  let request = markingReadRequests.get(requestKey);
  const joinedPendingRequest = request !== undefined;
  if (!request) {
    rememberConversationRead(conversationId);
    request = (async () => {
      try {
        await axios.post(
          inboxActions.conversations.read.url({
            conversation: conversationId,
          }),
        );

        return true;
      } catch (error) {
        const readConversationIds = new Set(locallyReadConversationIds.value);
        readConversationIds.delete(conversationId);
        locallyReadConversationIds.value = readConversationIds;

        console.warn('[inbox-read] 会话已读状态更新失败', {
          scope: 'system',
          conversationId,
          status: axios.isAxiosError(error)
            ? error.response?.status
            : undefined,
          code: axios.isAxiosError(error) ? error.code : undefined,
          errorType: axios.isAxiosError(error)
            ? 'AxiosError'
            : error instanceof Error
              ? error.name
              : typeof error,
        });

        return false;
      } finally {
        markingReadRequests.delete(requestKey);
      }
    })();
    markingReadRequests.set(requestKey, request);
  }

  const succeeded = await request;
  if (
    joinedPendingRequest &&
    options.ensureAfterPending &&
    !inboxNavigationInFlight() &&
    props.selection?.conversation.id === conversationId
  ) {
    await markConversationRead(conversationId, { reload: options.reload });
    return;
  }

  if (succeeded && options.reload) {
    reloadInboxListAndCounts();
  }
}

async function markCurrentConversationRead(): Promise<void> {
  const conversationId = props.selection?.conversation.id;
  if (!conversationId) return;

  const conversation = props.conversation_list.find(
    (item) => item.id === conversationId,
  );
  if (conversation && displayedUnreadCount(conversation) === 0) return;

  await markConversationRead(conversationId, { reload: true });
}

useInboxRealtimeRefresh({
  selectedConversationId: () => props.selection?.conversation.id,
  selectedContactId: () => props.selection?.contact.id,
  reloadListAndCounts: reloadInboxListAndCounts,
  reloadWithSelection: reloadInboxWithSelection,
  markConversationRead: (conversationId) =>
    markConversationRead(conversationId, { ensureAfterPending: true }),
  onConversationUnread: forgetConversationRead,
});

function selectConversation(conversation: ListConversationItemData): void {
  if (
    inboxNavigationState.value.threadId === conversation.thread_id &&
    props.selection !== null
  ) {
    return;
  }

  // 点击即清掉未读角标，不等选择请求返回。
  if (displayedUnreadCount(conversation) > 0) {
    void markConversationRead(conversation.id, { reload: true });
  }

  navigateInboxSelection({ threadId: conversation.thread_id, pane: null });
}

// 移动端单栏视图返回列表时明确请求列表面板。
function deselectConversation(): void {
  if (inboxNavigationState.value.threadId === null) {
    return;
  }

  navigateInboxSelection({ threadId: null, pane: 'list' });
}

type InboxTextReplyFormData = FormReplyInboxConversationData & {
  content: string;
};

const replyForm = useForm<InboxTextReplyFormData>({
  content: '',
  attachment_ids: [],
  client_msg_id: null,
  quoted_message_id: null,
  visitor_content: null,
  visitor_locale: null,
  source_locale: null,
});

const replyContentRef = computed({
  get: () => replyForm.content,
  set: (value: string) => {
    replyForm.content = value;
  },
});
const {
  replyQuote,
  quotedMessageId: replyQuotedMessageId,
  replyQuotePreviewOpen,
  replyQuotePreviewImages,
  replyQuotePreviewInitialId,
  replyQuoteTextDialogOpen,
  replyQuoteDialogTitle,
  replyQuoteDialogContent,
  quoteMessage,
  clearReplyQuote,
  openReplyQuoteTarget,
  reeditRecalledMessage,
} = useInboxReplyQuote({
  selection: selectionComputed,
  replyContent: replyContentRef,
  clearReplyContentError: () => replyForm.clearErrors('content'),
  focusComposer: focusReplyComposer,
  formatVisitorName,
});
let replyDraftRevision = 0;
watch(
  replyContentRef,
  () => {
    replyDraftRevision += 1;
  },
  { flush: 'sync' },
);

useInboxConversationPreviewAutoTranslate({
  conversationList: computed(() => props.conversation_list),
  sourceLocale: translationSourceLocale,
  targetLocale: translationTargetLocale,
  enabled: autoTranslateVisibleMessages,
});

const replyTranslation = useReplyTranslationPreview({
  selection: selectionComputed,
  replyContent: replyContentRef,
  enabled: autoTranslateReply,
});

const replyTranslationDraft = replyTranslation.draft;
const replyTranslationLoading = replyTranslation.loading;
const replyTranslationTouched = replyTranslation.touched;
const replyTranslationError = replyTranslation.error;
const replyExpectedVisitorLocale = replyTranslation.expectedVisitorLocale;
const replyTranslationRequirementMessage = replyTranslation.requirementMessage;
const showReplyTranslationPreview = replyTranslation.showPreview;
const replyTranslationTitle = replyTranslation.title;
watch(
  replyTranslationDraft,
  () => {
    replyDraftRevision += 1;
    replyTranslation.applyToForm(replyForm);
  },
  { flush: 'sync' },
);

const receptionActivityState = useReceptionActivityState();
let unsubscribeReceptionActivity: (() => void) | null = null;
// PageProps 在 setup 阶段初始化提示，Mercure 订阅只在浏览器挂载后建立。
let receptionActivityMounted = false;

function closeReceptionActivitySubscription(): void {
  unsubscribeReceptionActivity?.();
  unsubscribeReceptionActivity = null;
}

function syncReceptionActivitySubscription(): void {
  closeReceptionActivitySubscription();

  const conversationId = props.selection?.conversation.id ?? null;
  if (!receptionActivityMounted || conversationId === null) {
    return;
  }

  unsubscribeReceptionActivity = subscribeReceptionActivity(
    conversationId,
    receptionActivityState.apply,
  );
}

watch(
  () => props.selection?.conversation.id ?? null,
  () => {
    receptionActivityState.reset();
    const activity = props.selection?.agent_activity;
    if (activity) {
      receptionActivityState.apply(activity);
    }
    syncReceptionActivitySubscription();
  },
  { immediate: true },
);

// 同一会话的 Inertia 回源会更新活动快照，但不会触发会话 ID watcher。
watch(
  () => {
    const activity = props.selection?.agent_activity;

    return activity
      ? ([activity.active, activity.hold_ms, activity.revision] as const)
      : null;
  },
  () => {
    const activity = props.selection?.agent_activity;
    if (activity) {
      receptionActivityState.apply(activity);
    }
  },
);

onMounted(() => {
  receptionActivityMounted = true;
  syncReceptionActivitySubscription();
});

onUnmounted(() => {
  receptionActivityMounted = false;
  closeReceptionActivitySubscription();
});

const isAiReplying = computed(() => {
  const conversation = props.selection?.conversation;

  return (
    conversation?.status === 'open' &&
    conversation.inbox_status === 'ai_handling' &&
    receptionActivityState.active.value
  );
});

const {
  visibleUploads: visiblePendingReplyUploads,
  visibleUploadCount: pendingReplyUploadCount,
  uploading: replyAttachmentUploading,
  sending: replyAttachmentSending,
  error: replyAttachmentError,
  handleFileChange: handleReplyFileChange,
  handleImageChange: handleReplyImageChange,
  handlePaste: handleComposerPaste,
  removeUpload: removePendingReplyUpload,
  cancelCurrentFlows: cancelReplyAttachmentFlows,
} = useInboxReplyAttachments({
  selection: selectionComputed,
  quotedMessageId: replyQuotedMessageId,
  blocked: inboxInteractionBlocked,
  onQuoteConsumed: () => clearReplyQuote(),
  onScrollToBottom: () => scrollTimelineToBottom(),
  onSwitchAfterSent: scheduleMineViewAfterReply,
  onFocusAfterFinished: focusReplyComposer,
  onSendFailed: handleReplyWriteFailed,
});

// 请求协调器把附件直传视为阻塞写入，直传结束后由它补跑等待中的刷新。
watch(
  replyAttachmentUploading,
  (uploading) => {
    replyAttachmentTransferPending.value = uploading;
  },
  { immediate: true, flush: 'sync' },
);

const conversationCommandBlocked = computed(
  () => inboxInteractionBlocked.value || replyAttachmentUploading.value,
);
const contextPanelWriteBlocked = computed(
  () =>
    inboxForegroundInteractionBlocked.value || replyAttachmentUploading.value,
);
const {
  transferTeammates,
  isAiOwnedSelection,
  conversationCommandProcessing,
  importanceProcessing,
  updatingOnlineStatus,
  claimConversation,
  releaseConversationToAi,
  transferConversationToTeammate,
  reopenConversation,
  closeConversation,
  toggleSelectionImportance,
  switchCurrentUserOnline,
} = useInboxConversationCommands({
  selection: selectionComputed,
  teammates: () => props.teammates,
  currentUserId,
  commandsBlocked: conversationCommandBlocked,
  onChanged: reloadInboxWithSelection,
});

const isReplyActionDisabled = computed(
  () =>
    !props.selection?.can_reply ||
    inboxInteractionBlocked.value ||
    replyAttachmentUploading.value ||
    replyAttachmentSending.value ||
    replyForm.processing,
);

const canSubmitReply = computed(
  () =>
    !!props.selection?.can_reply &&
    !inboxInteractionBlocked.value &&
    !replyForm.processing &&
    !replyAttachmentUploading.value &&
    !replyAttachmentSending.value &&
    replyForm.content.trim().length > 0 &&
    replyTranslation.ready.value,
);
const {
  timelineScrollRef,
  activeStitchedTimeline,
  highlightedTimelineMessageId,
  timelineLoadingPrevious,
  timelineLoadingNext,
  anchorTimelineToMessage,
  handleTimelineScroll,
  handleTimelineMediaLoad,
  scrollTimelineToBottom,
} = useInboxTimelineWindow({
  selection: selectionComputed,
  pendingReplyUploadCount,
  isAiReplying,
});

const {
  autoTranslatingMessageIds,
  translateMessage,
  scheduleObserverRefresh: scheduleAutoTranslateObserverRefresh,
  stopObserverAndTimers: stopAutoTranslateObserverAndTimers,
} = useInboxAutoTranslate({
  selection: selectionComputed,
  sourceLocale: translationSourceLocale,
  targetLocale: translationTargetLocale,
  activeStitchedTimeline,
  timelineScrollRef,
  enabled: translationEnabled,
  conversationScopeId: translationConversationScopeId,
});

const {
  autoTranslatingSummaryIds,
  translateSummary,
  scheduleObserverRefresh: scheduleSummaryAutoTranslateObserverRefresh,
  stopObserverAndTimers: stopSummaryAutoTranslateObserverAndTimers,
} = useInboxSummaryAutoTranslate({
  selection: selectionComputed,
  sourceLocale: translationSourceLocale,
  targetLocale: translationTargetLocale,
  activeStitchedTimeline,
  timelineScrollRef,
  enabled: translationEnabled,
  conversationScopeId: translationConversationScopeId,
});

const replyPolish = useInboxReplyPolish({
  selection: selectionComputed,
  replyContent: replyContentRef,
  quotedMessageId: replyQuotedMessageId,
  modeOptions: replyAssistantModeOptions,
  toneOptions: replyPolishToneOptions,
  replyActionDisabled: isReplyActionDisabled,
});
const replyPolishOpen = replyPolish.open;
const replyPolishSelectedMode = replyPolish.selectedMode;
const replyPolishSelectedTone = replyPolish.selectedTone;
const validatedReplyAssistantModeOptions = replyPolish.validatedModeOptions;
const validatedReplyPolishToneOptions = replyPolish.validatedToneOptions;
const replyPolishCandidates = replyPolish.candidates;
const replyPolishLoading = replyPolish.loading;
const replyPolishError = replyPolish.error;
const canUseReplyPolish = replyPolish.canUse;
const replyPolishButtonTitle = replyPolish.buttonTitle;
const refreshReplyPolishCandidates = replyPolish.refreshCandidates;

async function applyReplyPolishCandidate(content: string): Promise<void> {
  await replyPolish.applyCandidate(content);
  replyComposerRef.value?.focus({ preventScroll: true });
}

function submitReply(): void {
  if (!canSubmitReply.value) {
    return;
  }

  const conversation = props.selection?.conversation;
  if (!conversation) {
    throw new Error('可提交回复时缺少当前会话');
  }

  const conversationId = conversation.id;
  const quotedMessageId = replyQuote.value?.id ?? null;
  replyTranslation.applyToForm(replyForm);
  const submittedDraftRevision = replyDraftRevision;
  let submittedSuccessfully = false;

  replyForm
    .transform((data): FormReplyInboxConversationData => ({
      content: data.content.trim(),
      attachment_ids: [],
      client_msg_id: null,
      quoted_message_id: quotedMessageId,
      visitor_content: data.visitor_content,
      visitor_locale: data.visitor_locale,
      source_locale: data.source_locale,
    }))
    .post(
      inboxActions.conversations.reply.url({
        conversation: conversationId,
      }),
      {
        preserveScroll: true,
        preserveState: true,
        // 只刷新当前会话和计数，保持列表中的附件地址稳定。
        only: ['selection', 'tab_counts'],
        onSuccess: () => {
          if (props.selection?.conversation.id !== conversationId) {
            return;
          }

          submittedSuccessfully = true;
          if (replyDraftRevision === submittedDraftRevision) {
            replyForm.reset(
              'content',
              'attachment_ids',
              'client_msg_id',
              'quoted_message_id',
              'visitor_content',
              'visitor_locale',
              'source_locale',
            );
            replyForm.clearErrors();
            replyTranslation.clear(replyForm);
          }
          if ((replyQuote.value?.id ?? null) === quotedMessageId) {
            clearReplyQuote();
          }
          void scrollTimelineToBottom();
        },
        onError: handleReplyWriteFailed,
        onNetworkError: handleReplyWriteFailed,
        onHttpException: handleReplyWriteFailed,
        onCancel: handleReplyWriteFailed,
        onFinish: () => {
          // 成功后的视图导航在表单结束后执行，避免嵌套 Inertia visit 将发送判为取消。
          if (submittedSuccessfully) {
            scheduleMineViewAfterReply(conversationId);
          }
          focusReplyComposer(conversationId);
        },
      },
    );
}

async function insertReplyEmoji(emoji: string): Promise<void> {
  if (isReplyActionDisabled.value) return;

  const composer = replyComposerRef.value;
  const start = composer?.selectionStart ?? replyForm.content.length;
  const end = composer?.selectionEnd ?? replyForm.content.length;

  replyForm.content = [
    replyForm.content.slice(0, start),
    emoji,
    replyForm.content.slice(end),
  ].join('');
  emojiPopoverOpen.value = false;

  await nextTick();

  const nextCursor = start + emoji.length;
  replyComposerRef.value?.focus({ preventScroll: true });
  replyComposerRef.value?.setSelectionRange(nextCursor, nextCursor);
}

// 收件箱页面选中可回复会话时持续续期活动租约，页面隐藏或切换会话即释放。
const TEAMMATE_ACTIVITY_RENEW_MS = 3000;
const teammateActivityHttp = axios.create();
const teammateActivityId = crypto.randomUUID();
let activeTeammateConversationId: string | null = null;
let teammateActivityRenewTimer: number | null = null;
let teammateActivityWarningConversationId: string | null = null;
let teammateActivitySequence = 0;

function renewTeammateConversationActivity(
  conversationId: string,
  active: boolean,
): void {
  const data = {
    activity_id: teammateActivityId,
    sequence: ++teammateActivitySequence,
    active,
  } satisfies FormRenewInboxConversationActivityData;

  void teammateActivityHttp
    .post(
      inboxActions.conversations.activity.url({
        conversation: conversationId,
      }),
      data,
    )
    .then(() => {
      if (teammateActivityWarningConversationId === conversationId) {
        teammateActivityWarningConversationId = null;
      }
    })
    .catch((error: unknown) => {
      // 同一会话只记录一次，避免续期失败持续刷日志。
      if (teammateActivityWarningConversationId === conversationId) {
        return;
      }

      teammateActivityWarningConversationId = conversationId;
      console.warn('[inbox-activity] 客服会话活动续期失败', {
        scope: 'system',
        conversationId,
        active,
        status: axios.isAxiosError(error) ? error.response?.status : undefined,
        code: axios.isAxiosError(error) ? error.code : undefined,
        message: error instanceof Error ? error.message : String(error),
        errorType: axios.isAxiosError(error)
          ? 'AxiosError'
          : error instanceof Error
            ? error.name
            : typeof error,
      });
    });
}

function stopTeammateActivityRenewal(): void {
  if (teammateActivityRenewTimer) {
    window.clearInterval(teammateActivityRenewTimer);
    teammateActivityRenewTimer = null;
  }
}

function syncTeammateConversationActivity(): void {
  const nextConversationId =
    document.visibilityState === 'visible' && props.selection?.can_reply
      ? props.selection.conversation.id
      : null;

  if (activeTeammateConversationId === nextConversationId) {
    return;
  }

  stopTeammateActivityRenewal();

  if (activeTeammateConversationId) {
    renewTeammateConversationActivity(activeTeammateConversationId, false);
  }

  activeTeammateConversationId = nextConversationId;
  if (!nextConversationId) {
    return;
  }

  renewTeammateConversationActivity(nextConversationId, true);
  teammateActivityRenewTimer = window.setInterval(() => {
    renewTeammateConversationActivity(nextConversationId, true);
  }, TEAMMATE_ACTIVITY_RENEW_MS);
}

watch(
  () =>
    [
      props.selection?.conversation.id ?? null,
      props.selection?.can_reply,
    ] as const,
  syncTeammateConversationActivity,
  { immediate: true },
);

onMounted(() => {
  document.addEventListener(
    'visibilitychange',
    syncTeammateConversationActivity,
  );
});

onUnmounted(() => {
  document.removeEventListener(
    'visibilitychange',
    syncTeammateConversationActivity,
  );
  stopTeammateActivityRenewal();
  if (activeTeammateConversationId) {
    renewTeammateConversationActivity(activeTeammateConversationId, false);
    activeTeammateConversationId = null;
  }
});

function detectCannedReplyTrigger(): void {
  if (!props.selection?.can_reply) {
    cannedReplyPickerOpen.value = false;
    return;
  }

  const composer = replyComposerRef.value;
  if (!composer) {
    return;
  }

  const cursor = composer.selectionStart ?? replyForm.content.length;
  const before = replyForm.content.slice(0, cursor);
  // 仅识别行首的快捷指令，避免把网址路径当作指令。
  const match = /(^|\n)\/(\S{0,32})$/u.exec(before);

  if (!match) {
    if (cannedReplyPickerOpen.value) {
      cannedReplyPickerOpen.value = false;
      cannedReplyTriggerRange.value = null;
    }
    return;
  }

  const slashIndex = before.length - match[2].length - 1;
  cannedReplyTriggerRange.value = { start: slashIndex, end: cursor };
  cannedReplyPickerQuery.value = match[2];
  cannedReplyPickerOpen.value = true;
}

function openCannedReplyPicker(): void {
  if (isReplyActionDisabled.value) {
    return;
  }
  cannedReplyTriggerRange.value = null;
  cannedReplyPickerQuery.value = '';
  cannedReplyPickerOpen.value = true;
}

async function applyCannedReplyContent(payload: {
  rendered_content: string;
  warnings?: string[];
}): Promise<void> {
  const composer = replyComposerRef.value;
  const range = cannedReplyTriggerRange.value;

  if (!composer) {
    replyForm.content += payload.rendered_content;
  } else if (range) {
    const before = replyForm.content.slice(0, range.start);
    const after = replyForm.content.slice(range.end);
    replyForm.content = `${before}${payload.rendered_content}${after}`;
  } else {
    const start = composer.selectionStart ?? replyForm.content.length;
    const end = composer.selectionEnd ?? start;
    const before = replyForm.content.slice(0, start);
    const after = replyForm.content.slice(end);
    replyForm.content = `${before}${payload.rendered_content}${after}`;
  }

  cannedReplyTriggerRange.value = null;
  cannedReplyPickerQuery.value = '';
  cannedReplyPickerOpen.value = false;

  if (payload.warnings?.length) {
    toast.warning(payload.warnings.join('\n'));
  }

  await nextTick();
  composer?.focus({ preventScroll: true });
}

function handleComposerKeydown(event: KeyboardEvent): void {
  if (cannedReplyPickerOpen.value) {
    if (
      event.key === 'ArrowDown' ||
      event.key === 'ArrowUp' ||
      event.key === 'Escape' ||
      (event.key === 'Enter' && !event.isComposing)
    ) {
      event.preventDefault();
      cannedReplyPickerRef.value?.handleKeydown(event);
      return;
    }
  }

  if (
    event.key !== 'Enter' ||
    event.shiftKey ||
    event.metaKey ||
    event.ctrlKey ||
    event.altKey ||
    event.isComposing
  ) {
    return;
  }

  event.preventDefault();
  submitReply();
}

async function focusReplyComposer(conversationId: string): Promise<void> {
  if (typeof window === 'undefined') return;

  await nextTick();

  window.requestAnimationFrame(() => {
    if (props.selection?.conversation.id !== conversationId) return;
    if (!props.selection?.can_reply) return;

    replyComposerRef.value?.focus({ preventScroll: true });
  });
}

watch(translationEnabled, (value) => {
  if (value) {
    scheduleAutoTranslateObserverRefresh();
    scheduleSummaryAutoTranslateObserverRefresh();
    return;
  }

  stopAutoTranslateObserverAndTimers();
  stopSummaryAutoTranslateObserverAndTimers();
});

watch(autoTranslateReply, (value) => {
  if (value) {
    replyTranslation.schedule();
    return;
  }

  replyTranslation.clear(replyForm);
});

watch(
  () => props.selection?.conversation.id,
  () => {
    // 会话切换时清空回复草稿，防止内容进入其他会话。
    cannedReplyPickerOpen.value = false;
    cannedReplyPickerQuery.value = '';
    cannedReplyTriggerRange.value = null;
    replyForm.reset();
    replyForm.clearErrors();
    replyTranslation.clear(replyForm);
  },
);

function recallMessage(conversationId: string, messageId: string): void {
  if (conversationCommandBlocked.value) {
    return;
  }

  router.post(
    inboxActions.conversations.messages.recall.url({
      conversation: conversationId,
      message: messageId,
    }),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: reloadInboxWithSelection,
    },
  );
}

function retryMessage(conversationId: string, messageId: string): void {
  if (conversationCommandBlocked.value) {
    return;
  }

  router.post(
    inboxActions.conversations.messages.retry.url({
      conversation: conversationId,
      message: messageId,
    }),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: reloadInboxWithSelection,
    },
  );
}

const claimButtonLabel = computed(() => {
  if (isAiOwnedSelection.value) {
    return t('我来接待');
  }

  const assignedUserId = props.selection?.conversation.assigned_user_id;
  if (assignedUserId && assignedUserId !== currentUserId.value) {
    return t('由我接手');
  }

  return t('开始接待');
});

/**
 * 只展示对当前用户有行动意义的收件箱状态。
 */
interface InboxStatusBadge {
  label: string;
}

function inboxStatusBadgeForCurrent(
  status: ConversationStatus,
  inboxStatus: ConversationInboxStatus,
  inboxStatusLabel: string,
  waitingForVisitorReplyLabel: string | null,
  assignedUserId: string | null,
  assignedUserName: string | null,
): InboxStatusBadge | null {
  if (status === 'closed') {
    return null;
  }

  if (waitingForVisitorReplyLabel) {
    return { label: waitingForVisitorReplyLabel };
  }

  switch (inboxStatus) {
    case 'ai_handling':
    case 'teammate_pending':
      return { label: inboxStatusLabel };
    case 'teammate_handling':
      if (assignedUserId === currentUserId.value) {
        return { label: t('我负责的') };
      }
      if (assignedUserName) {
        return { label: t('由 {name} 接待', { name: assignedUserName }) };
      }

      return { label: inboxStatusLabel };
  }

  throw new Error(`未知的收件箱会话状态：${inboxStatus}`);
}

function selectionInboxStatusLabel(
  conversation: ConversationSummaryData,
): string | null {
  return (
    inboxStatusBadgeForCurrent(
      conversation.status,
      conversation.inbox_status,
      conversation.inbox_status_label,
      conversation.waiting_for_visitor_reply_label,
      conversation.assigned_user_id,
      conversation.assigned_user_name,
    )?.label ?? null
  );
}
</script>

<template>
  <Head :title="t('收件箱')" />
  <div
    class="relative flex h-dvh min-h-0 overflow-hidden md:h-[calc(100dvh-1rem)]"
  >
    <InboxConversationSidebar
      ref="inboxConversationSidebarRef"
      :has-selection="props.selection !== null"
      :current-view="inboxNavigationState.filters.view"
      :current-channel-id="inboxNavigationState.filters.channelId"
      :current-assignee="inboxNavigationState.filters.assignee"
      :current-search="inboxNavigationState.filters.search"
      :search-input-value="searchInputValue"
      :current-important-only="inboxNavigationState.filters.importantOnly"
      :current-thread-id="inboxNavigationState.threadId"
      :enabled-web-channels="props.enabled_web_channels"
      :teammates="props.teammates"
      :tab-counts="props.tab_counts"
      :search-panel-active="searchPanelActive"
      :search-scope-contact="searchScopeContact"
      :recent-search-keywords="recentSearchKeywords"
      :global-search-active="globalSearchActive"
      :global-search-loading="globalSearchLoading"
      :global-search-failed="globalSearchFailed"
      :global-search-empty="globalSearchEmpty"
      :global-contact-search-results="globalContactSearchResults"
      :global-message-search-results="globalMessageSearchResults"
      :conversation-list="props.conversation_list"
      :loading-more-conversations="loadingMoreConversations"
      :conversation-preview="conversationListPreview"
      :conversation-unread-count="displayedUnreadCount"
      @update:search-panel-active="onSearchPanelActiveChange"
      @remove-search-scope="removeSearchScope"
      @clear-recent-search-keywords="clearRecentSearchKeywords"
      @apply-recent-search-keyword="applyRecentSearchKeyword"
      @search-input="updateSearchInput"
      @search-enter="commitSearchInput"
      @search-exit="exitSearchPanel"
      @navigate="navigateInbox"
      @select-contact-search-result="openGlobalContactSearchResult"
      @select-message-search-result="openGlobalMessageSearchResult"
      @select-conversation="selectConversation"
      @scroll="handleConversationListScroll"
    />

    <section
      class="min-h-0 min-w-0 flex-1 flex-col"
      :class="props.selection && !searchPanelActive ? 'flex' : 'hidden md:flex'"
    >
      <template v-if="props.selection">
        <InboxConversationHeader
          v-model:translation-popover-open="translationPopoverOpen"
          v-model:translation-source-locale="translationSourceLocale"
          v-model:translation-target-locale="translationTargetLocale"
          v-model:auto-translate-visible-messages="autoTranslateVisibleMessages"
          :selection="props.selection"
          :inbox-status-label="
            selectionInboxStatusLabel(props.selection.conversation)
          "
          :importance-processing="importanceProcessing"
          :claim-button-label="claimButtonLabel"
          :transfer-teammates="transferTeammates"
          :conversation-command-processing="conversationCommandProcessing"
          :translation-enabled="translationEnabled"
          :translation-source-options="translationSourceOptions"
          :reception-language-options="props.reception_language_options"
          :show-timeline-events="showTimelineEvents"
          @back="deselectConversation"
          @open-context="inboxContextPaneRef?.openMobile()"
          @search="openConversationScopedSearch"
          @claim="claimConversation"
          @transfer="transferConversationToTeammate"
          @release-to-ai="releaseConversationToAi"
          @translate="translateCurrentConversation"
          @toggle-timeline-events="toggleTimelineEvents"
          @reopen="reopenConversation"
          @close="closeConversation"
          @toggle-importance="toggleSelectionImportance"
        />

        <div
          ref="timelineScrollRef"
          class="min-h-0 flex-1 overflow-y-auto px-6 pb-3"
          @load.capture="handleTimelineMediaLoad"
          @scroll="handleTimelineScroll"
        >
          <div v-if="activeStitchedTimeline" class="w-full">
            <div
              v-if="props.selection.conversation.summary"
              class="sticky top-0 z-10 -mx-6 bg-background px-6 pb-3"
            >
              <ConversationSummaryBlock
                :data-inbox-conversation-summary-id="
                  props.selection.conversation.id
                "
                :conversation="props.selection.conversation"
                :translation-locale="translationTargetLocale"
                :available-tags="props.available_conversation_tags"
                :is-translating="
                  autoTranslatingSummaryIds.has(props.selection.conversation.id)
                "
                :translation-enabled="translationEnabled"
                :translation-available="props.selection.can_translate_messages"
                variant="current"
                @translate="
                  (force) =>
                    translateSummary(props.selection!.conversation.id, force)
                "
              />
            </div>
            <div
              v-if="timelineLoadingPrevious"
              class="py-2 text-center text-xs text-muted-foreground"
            >
              {{ t('加载中...') }}
            </div>
            <InboxStitchedTimeline
              :timeline="activeStitchedTimeline"
              :contact-summary="props.selection.contact"
              :current-conversation-id="props.selection.conversation.id"
              :current-assigned-user-id="
                props.selection.conversation.assigned_user_id
              "
              :current-user-id="currentUserId"
              :can-reply-in-current="props.selection.can_reply"
              :message-commands-disabled="conversationCommandBlocked"
              :translating-message-ids="autoTranslatingMessageIds"
              :translating-summary-ids="autoTranslatingSummaryIds"
              :translation-locale="translationTargetLocale"
              :translation-enabled="translationEnabled"
              :translation-available="props.selection.can_translate_messages"
              :available-conversation-tags="props.available_conversation_tags"
              :show-events="showTimelineEvents"
              :highlighted-message-id="highlightedTimelineMessageId"
              @recall="recallMessage"
              @retry="retryMessage"
              @reedit="reeditRecalledMessage"
              @quote="quoteMessage"
              @translate-message="
                (p) => translateMessage(p.conversationId, p.messageId, p.force)
              "
              @translate-summary="
                (p) => translateSummary(p.conversationId, p.force)
              "
            />
            <div
              v-if="timelineLoadingNext"
              class="py-2 text-center text-xs text-muted-foreground"
            >
              {{ t('加载中...') }}
            </div>
            <div
              v-if="isAiReplying"
              class="mt-3 flex justify-end"
              aria-live="polite"
              :aria-label="t('AI 正在回复')"
            >
              <div
                class="mr-10 flex items-center gap-1.5 rounded-2xl rounded-br-sm bg-muted px-3 py-2 text-foreground"
              >
                <span class="sr-only">{{ t('AI 正在回复') }}</span>
                <span
                  class="size-1.5 animate-bounce rounded-full bg-current opacity-60 [animation-delay:-0.3s]"
                />
                <span
                  class="size-1.5 animate-bounce rounded-full bg-current opacity-60 [animation-delay:-0.15s]"
                />
                <span
                  class="size-1.5 animate-bounce rounded-full bg-current opacity-60"
                />
              </div>
            </div>
          </div>
        </div>

        <footer class="shrink-0 border-t border-border/60 bg-background p-2">
          <div
            v-if="props.selection.can_reply && isCurrentUserOffline"
            class="mb-2 flex flex-col items-stretch gap-2 rounded-md border bg-muted/30 px-3 py-2 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
          >
            <span class="min-w-0 flex-1 leading-5">
              {{
                t('你当前离线。可以继续回复此会话，但不会接收新的转人工会话。')
              }}
            </span>
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="h-7 w-full rounded-md px-2 text-xs sm:w-auto"
              :disabled="updatingOnlineStatus || conversationCommandBlocked"
              @click="switchCurrentUserOnline"
            >
              {{ t('设为在线') }}
            </Button>
          </div>
          <div
            v-if="replyForm.errors.content"
            class="mb-2 text-xs text-destructive"
          >
            {{ replyForm.errors.content }}
          </div>
          <div
            v-if="replyAttachmentError"
            class="mb-2 text-xs text-destructive"
          >
            {{ replyAttachmentError }}
          </div>
          <InboxPendingReplyUploads
            :uploads="visiblePendingReplyUploads"
            @remove="removePendingReplyUpload"
          />
          <div
            v-if="showReplyTranslationPreview"
            class="mb-2 rounded-md border bg-muted/30 px-3 py-2"
          >
            <div
              class="mb-1 flex items-center justify-between gap-2 text-xs text-muted-foreground"
            >
              <span>{{ replyTranslationTitle }}</span>
              <span v-if="replyTranslationLoading">{{ t('翻译中') }}</span>
            </div>
            <Textarea
              v-model="replyTranslationDraft"
              rows="2"
              class="min-h-16 resize-y bg-background text-sm"
              :disabled="
                replyExpectedVisitorLocale === null ||
                (replyTranslationLoading && !replyTranslationDraft)
              "
              @input="replyTranslationTouched = true"
            />
            <div
              v-if="replyTranslationRequirementMessage"
              class="mt-1 text-xs"
              :class="
                replyTranslationError || replyExpectedVisitorLocale === null
                  ? 'text-destructive'
                  : 'text-muted-foreground'
              "
            >
              {{ replyTranslationRequirementMessage }}
            </div>
          </div>
          <div
            class="overflow-hidden rounded-xl border border-input bg-background shadow-xs transition-[box-shadow,border-color] duration-200 focus-within:border-foreground/20 focus-within:shadow-sm dark:bg-neutral-950"
            :class="{ 'opacity-60': !props.selection.can_reply }"
          >
            <input
              ref="replyFileInputRef"
              type="file"
              class="sr-only"
              multiple
              :disabled="isReplyActionDisabled"
              @change="handleReplyFileChange"
            />
            <input
              ref="replyImageInputRef"
              type="file"
              class="sr-only"
              multiple
              accept="image/*"
              :disabled="isReplyActionDisabled"
              @change="handleReplyImageChange"
            />
            <div class="relative">
              <textarea
                ref="replyComposerRef"
                v-model="replyForm.content"
                :disabled="isReplyActionDisabled"
                class="block h-36 w-full resize-none overflow-y-auto bg-transparent px-3 pt-3 pb-6 text-sm leading-7 outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed"
                @keydown="handleComposerKeydown"
                @paste="handleComposerPaste"
                @input="detectCannedReplyTrigger"
                @keyup="detectCannedReplyTrigger"
                @click="detectCannedReplyTrigger"
              ></textarea>
              <div
                v-if="replyQuote"
                class="absolute inset-x-3 bottom-1 flex items-center gap-2 text-xs text-muted-foreground"
              >
                <button
                  type="button"
                  class="flex min-w-0 flex-1 items-center text-left transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                  @click="openReplyQuoteTarget(replyQuote)"
                >
                  <span class="max-w-[45%] shrink-0 truncate font-medium">
                    {{ replyQuote.senderName }}：
                  </span>
                  <span class="min-w-0 truncate">
                    {{ replyQuote.preview }}
                  </span>
                </button>
                <button
                  type="button"
                  class="inline-flex size-5 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                  :aria-label="t('取消引用')"
                  :title="t('取消引用')"
                  @click="clearReplyQuote"
                >
                  <X class="size-3.5" />
                </button>
              </div>
            </div>
            <div
              class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1 px-3 pt-1 pb-2"
            >
              <div class="flex items-center gap-1.5">
                <Popover v-model:open="emojiPopoverOpen">
                  <PopoverTrigger as-child>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="size-6 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:text-muted-foreground/50"
                      :disabled="isReplyActionDisabled"
                      :aria-label="t('选择表情')"
                      :title="t('选择表情')"
                    >
                      <Smile class="size-4" />
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent class="w-64 p-2" align="start">
                    <div class="max-h-48 overflow-y-auto">
                      <div class="grid grid-cols-7 gap-1">
                        <button
                          v-for="emoji in COMPOSER_EMOJIS"
                          :key="emoji"
                          type="button"
                          class="flex size-7 items-center justify-center rounded-md text-base transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                          :aria-label="t('选择表情')"
                          @click="insertReplyEmoji(emoji)"
                        >
                          {{ emoji }}
                        </button>
                      </div>
                    </div>
                  </PopoverContent>
                </Popover>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="size-6 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:text-muted-foreground/50"
                  :disabled="isReplyActionDisabled"
                  :aria-label="t('添加附件')"
                  :title="t('添加附件')"
                  @click="replyFileInputRef?.click()"
                >
                  <Paperclip class="size-4" />
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="size-6 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:text-muted-foreground/50"
                  :disabled="isReplyActionDisabled"
                  :aria-label="t('添加图片')"
                  :title="t('添加图片')"
                  @click="replyImageInputRef?.click()"
                >
                  <ImageIcon class="size-4" />
                </Button>
                <CannedReplyPicker
                  ref="cannedReplyPickerRef"
                  v-model:open="cannedReplyPickerOpen"
                  :conversation-id="props.selection.conversation.id"
                  :query="cannedReplyPickerQuery"
                  @rendered="applyCannedReplyContent"
                >
                  <template #trigger>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="size-6 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:text-muted-foreground/50"
                      :disabled="isReplyActionDisabled"
                      :aria-label="t('快捷回复')"
                      :title="t('选择快捷回复')"
                      @click="openCannedReplyPicker"
                    >
                      <MessageSquareQuote class="size-4" />
                    </Button>
                  </template>
                </CannedReplyPicker>
                <InboxReplyPolishPopover
                  v-model:open="replyPolishOpen"
                  v-model:selected-mode="replyPolishSelectedMode"
                  v-model:selected-tone="replyPolishSelectedTone"
                  :mode-options="validatedReplyAssistantModeOptions"
                  :tone-options="validatedReplyPolishToneOptions"
                  :candidates="replyPolishCandidates"
                  :loading="replyPolishLoading"
                  :can-use="canUseReplyPolish"
                  :button-title="replyPolishButtonTitle"
                  :error="replyPolishError"
                  @refresh="refreshReplyPolishCandidates"
                  @apply="applyReplyPolishCandidate"
                />
                <Button
                  v-if="
                    props.selection.can_reply &&
                    replyExpectedVisitorLocale !== null &&
                    props.selection.can_translate_messages
                  "
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="size-6 rounded-md hover:bg-muted hover:text-foreground disabled:text-muted-foreground/50"
                  :class="
                    autoTranslateReply
                      ? 'bg-muted text-foreground'
                      : 'text-muted-foreground'
                  "
                  :aria-label="replyAutoTranslateToggleTitle"
                  :aria-pressed="autoTranslateReply"
                  :title="replyAutoTranslateToggleTitle"
                  :disabled="isReplyActionDisabled"
                  @click="toggleReplyAutoTranslate"
                >
                  <Languages class="size-4" />
                </Button>
              </div>
              <Button
                size="sm"
                class="h-7 rounded-md bg-foreground px-3 text-xs text-background shadow-none hover:bg-foreground/90 disabled:bg-muted disabled:text-muted-foreground"
                :disabled="!canSubmitReply"
                @click="submitReply"
              >
                {{ t('发送') }}
              </Button>
            </div>
          </div>
        </footer>
      </template>

      <div
        v-else
        class="flex min-h-0 flex-1 items-center justify-center text-sm text-muted-foreground"
      >
        {{ t('请选择一个会话查看消息') }}
      </div>
    </section>

    <InboxContextPane
      ref="inboxContextPaneRef"
      :selection="props.selection"
      :available-contact-tags="props.available_contact_tags"
      :target-locale="translationTargetLocale"
      :translation-enabled="translationEnabled"
      :write-blocked="contextPanelWriteBlocked"
      @write-pending-change="contextWritePending = $event"
      @write-failed="handleContextWriteFailed"
    />
  </div>

  <ImagePreviewDialog
    v-if="replyQuotePreviewImages.length"
    v-model:open="replyQuotePreviewOpen"
    :images="replyQuotePreviewImages"
    :initial-id="replyQuotePreviewInitialId"
  />
  <Dialog v-model:open="replyQuoteTextDialogOpen">
    <DialogContent class="max-h-[80vh] overflow-y-auto sm:max-w-lg">
      <DialogHeader>
        <DialogTitle>{{ replyQuoteDialogTitle }}</DialogTitle>
      </DialogHeader>
      <div class="text-sm leading-6 whitespace-pre-wrap">
        {{ replyQuoteDialogContent }}
      </div>
    </DialogContent>
  </Dialog>
</template>
