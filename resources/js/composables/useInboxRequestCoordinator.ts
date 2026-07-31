/**
 * 串行化收件箱的显式导航、后台刷新、搜索状态写入与业务写请求。
 *
 * 收件箱同一时间可能有多路请求在跑：用户切换筛选或会话的显式导航、实时事件触发的
 * 后台局部刷新、联系人资料的自动保存、附件直传与回复发送。这些请求返回的 props
 * 会互相覆盖，因此写请求期间暂停后台刷新，导航与搜索写入排队等待，等阻塞结束后
 * 只补跑最后一次。
 */
import { useI18n } from '@/composables/useI18n';
import { useToast } from '@/composables/useToast';
import { isInboxContextReloadAllowed } from '@/pages/inbox/inboxContextReloadGate';
import { router } from '@inertiajs/vue3';
import {
  computed,
  onUnmounted,
  ref,
  toValue,
  watch,
  type ComputedRef,
  type MaybeRefOrGetter,
} from 'vue';

/** 后台刷新范围，选择优先于列表，列表优先于翻页。 */
export type InboxRefreshScope = 'load-more' | 'list' | 'selection';

/** 阻塞期间可缓存的补刷范围。 */
export type InboxReloadScope = 'list' | 'selection';

const INBOX_REFRESH_PRIORITY: Record<InboxRefreshScope, number> = {
  'load-more': 1,
  list: 2,
  selection: 3,
};

interface UseInboxRequestCoordinatorOptions {
  conversationId: MaybeRefOrGetter<string | null>;
  /** 联系人资料面板正在等待或执行保存。 */
  contextWritePending: MaybeRefOrGetter<boolean>;
  /** 回复附件正在直传。 */
  attachmentTransferPending: MaybeRefOrGetter<boolean>;
  /** 用服务端 PageProps 覆盖本地导航状态。 */
  syncNavigationStateFromProps: () => void;
  /** 显式导航前终止当前上下文的附件流程。 */
  cancelAttachmentFlows: () => void;
  /** 从列表首页刷新会话列表与计数。 */
  reloadListAndCounts: () => void;
  /** 刷新会话列表、计数与当前选择。 */
  reloadWithSelection: () => void;
  /** 把搜索词写入当前页面状态，写入结束后调用 onFinish。 */
  writeSearchState: (search: string, onFinish: () => void) => void;
}

interface UseInboxRequestCoordinatorReturn {
  /** 前台交互是否被已经发出的业务写请求或显式导航阻塞。 */
  foregroundInteractionBlocked: ComputedRef<boolean>;
  /** 交互是否被任一在途写入或显式导航阻塞。 */
  interactionBlocked: ComputedRef<boolean>;
  /** 在阻塞结束后启动最新一次显式导航。 */
  runNavigation: (start: () => void) => void;
  prepareNavigation: () => void;
  finishNavigation: () => void;
  /** 取消等待中的显式导航，返回调用时是否确有导航在等待。 */
  cancelWaitingNavigation: () => boolean;
  /**
   * 是否有显式导航在排队等待。
   *
   * 等待中的导航按本地导航状态生成地址，此时服务端 props 不能覆盖本地状态。
   */
  hasWaitingNavigation: () => boolean;
  /** 是否有显式导航正在等待或已经发出。 */
  navigationInFlight: () => boolean;
  /** 后台请求（列表刷新、翻页）当前是否应让位给在途写入或导航。 */
  backgroundRequestBlocked: () => boolean;
  beginRefresh: (scope: InboxRefreshScope) => number | null;
  trackRefresh: (refreshId: number, token: { cancel: () => void }) => void;
  finishRefresh: (refreshId: number) => void;
  cancelRefresh: () => void;
  deferReload: (scope: InboxReloadScope) => void;
  /** 提交搜索状态写入，被阻塞时缓存最后一次搜索词。 */
  requestSearchStateWrite: (search: string) => void;
  /** 等待写入的搜索词，导航状态同步时需要保留它。 */
  deferredSearch: () => string | undefined;
}

/** 创建收件箱请求协调器，并接管 Inertia 写请求的生命周期监听。 */
export function useInboxRequestCoordinator(
  options: UseInboxRequestCoordinatorOptions,
): UseInboxRequestCoordinatorReturn {
  const { t } = useI18n();
  const { toast } = useToast();

  const activeNavigationCount = ref(0);
  const activeWriteVisitCount = ref(0);
  const pendingWriteStarts = new Set<object>();
  const activeWriteVisits = new Set<object>();

  let refreshSequence = 0;
  let activeRefresh: {
    id: number;
    scope: InboxRefreshScope;
    cancel: (() => void) | null;
  } | null = null;
  let deferredReload: InboxReloadScope | null = null;
  let deferredSearchValue: string | undefined;
  let searchWritePending = false;
  let pendingNavigation: (() => void) | null = null;

  const writeProcessing = computed(() => activeWriteVisitCount.value > 0);
  const foregroundInteractionBlocked = computed(
    () => writeProcessing.value || activeNavigationCount.value > 0,
  );
  const interactionBlocked = computed(
    () =>
      foregroundInteractionBlocked.value ||
      toValue(options.contextWritePending),
  );

  function hasWriteInFlight(): boolean {
    return pendingWriteStarts.size > 0 || activeWriteVisitCount.value > 0;
  }

  /** 是否存在会被请求响应覆盖的在途写入。 */
  function hasBlockingWrite(): boolean {
    return (
      toValue(options.contextWritePending) ||
      toValue(options.attachmentTransferPending) ||
      hasWriteInFlight()
    );
  }

  function backgroundRequestBlocked(): boolean {
    return (
      activeNavigationCount.value > 0 ||
      hasBlockingWrite() ||
      searchWritePending ||
      pendingNavigation !== null
    );
  }

  /** 取消列表或选择的在途刷新，避免响应覆盖显式导航。 */
  function cancelRefresh(): void {
    const refresh = activeRefresh;
    activeRefresh = null;
    refresh?.cancel?.();
  }

  function deferReload(scope: InboxReloadScope): void {
    if (scope === 'selection' || deferredReload === null) {
      deferredReload = scope;
    }
  }

  /** 业务写入期间暂停后台刷新，并在写入完成后补刷必要范围。 */
  function pauseRefreshForWrite(): void {
    const refreshScope = activeRefresh?.scope;
    if (refreshScope === 'list' || refreshScope === 'selection') {
      deferReload(refreshScope);
    }

    cancelRefresh();
  }

  /** 按 selection、list、load-more 的优先级创建后台刷新。 */
  function beginRefresh(scope: InboxRefreshScope): number | null {
    if (activeRefresh !== null) {
      if (
        INBOX_REFRESH_PRIORITY[scope] <=
        INBOX_REFRESH_PRIORITY[activeRefresh.scope]
      ) {
        if (scope !== 'load-more') {
          deferReload(scope);
        }

        return null;
      }

      cancelRefresh();
    }

    const refreshId = ++refreshSequence;
    activeRefresh = { id: refreshId, scope, cancel: null };

    return refreshId;
  }

  function trackRefresh(
    refreshId: number,
    token: { cancel: () => void },
  ): void {
    if (activeRefresh?.id !== refreshId) {
      token.cancel();

      return;
    }

    activeRefresh.cancel = () => token.cancel();
  }

  function finishRefresh(refreshId: number): void {
    if (activeRefresh?.id !== refreshId) {
      return;
    }

    activeRefresh = null;
    flushDeferredWork();
  }

  /** 切换会话或筛选前终止当前上下文的后台刷新与附件流程。 */
  function prepareNavigation(): void {
    activeNavigationCount.value += 1;
    cancelRefresh();
    options.cancelAttachmentFlows();
  }

  /** 在所有显式收件箱导航结束后执行期间合并的最新刷新。 */
  function finishNavigation(): void {
    if (activeNavigationCount.value === 0) {
      console.warn('[inbox-navigation] 导航结束回调缺少对应的开始状态', {
        instance: 'system',
        conversationId: toValue(options.conversationId),
        deferredReload,
      });

      return;
    }

    activeNavigationCount.value -= 1;
    if (activeNavigationCount.value > 0) {
      return;
    }

    options.syncNavigationStateFromProps();
    flushDeferredWork();
  }

  /** 在搜索状态写入或业务写请求结束后启动最新一次显式导航。 */
  function runNavigation(start: () => void): void {
    if (searchWritePending || hasBlockingWrite()) {
      pendingNavigation = start;

      return;
    }

    start();
  }

  function cancelWaitingNavigation(): boolean {
    const navigationWasPending = pendingNavigation !== null;
    pendingNavigation = null;
    options.syncNavigationStateFromProps();

    return navigationWasPending;
  }

  /** 判断搜索状态写入是否需要等待在途导航、后台刷新或业务写请求。 */
  function searchUpdateBlocked(): boolean {
    return (
      searchWritePending ||
      activeNavigationCount.value > 0 ||
      activeRefresh !== null ||
      hasBlockingWrite()
    );
  }

  function requestSearchStateWrite(search: string): void {
    if (searchUpdateBlocked()) {
      deferredSearchValue = search;

      return;
    }

    deferredSearchValue = undefined;
    searchWritePending = true;
    options.writeSearchState(search, () => {
      searchWritePending = false;
      flushDeferredWork();
    });
  }

  /** 依次执行最新导航、搜索状态写入和后台刷新。 */
  function flushDeferredWork(): void {
    if (
      searchWritePending ||
      activeNavigationCount.value > 0 ||
      hasBlockingWrite()
    ) {
      return;
    }

    if (pendingNavigation !== null) {
      const start = pendingNavigation;
      pendingNavigation = null;
      // 导航地址读取本地状态，其中已经包含最后一次搜索词。
      deferredSearchValue = undefined;
      start();

      return;
    }

    if (deferredSearchValue !== undefined) {
      requestSearchStateWrite(deferredSearchValue);

      return;
    }

    if (activeRefresh !== null || deferredReload === null) {
      return;
    }

    const reload = deferredReload;
    deferredReload = null;
    if (reload === 'selection') {
      options.reloadWithSelection();

      return;
    }

    options.reloadListAndCounts();
  }

  const removeWriteBeforeListener = router.on('before', (event) => {
    const visit = event.detail.visit;
    if (visit.method === 'get') {
      if (
        !visit.prefetch &&
        !isInboxContextReloadAllowed() &&
        hasBlockingWrite()
      ) {
        pendingNavigation = null;
        options.syncNavigationStateFromProps();
        toast.info(t('当前操作正在完成，请稍后再离开'));
        console.info('[inbox-navigation] 当前写入阻止离开收件箱', {
          instance: 'system',
          conversationId: toValue(options.conversationId),
        });

        return false;
      }

      return;
    }

    pauseRefreshForWrite();
    pendingWriteStarts.add(visit);
    queueMicrotask(() => {
      if (pendingWriteStarts.delete(visit)) {
        flushDeferredWork();
      }
    });
  });

  const removeWriteStartListener = router.on('start', (event) => {
    const visit = event.detail.visit;
    if (visit.method === 'get') {
      return;
    }

    activeWriteVisits.add(visit);
    activeWriteVisitCount.value = activeWriteVisits.size;
  });

  const removeWriteFinishListener = router.on('finish', (event) => {
    if (!activeWriteVisits.delete(event.detail.visit)) {
      return;
    }

    activeWriteVisitCount.value = activeWriteVisits.size;
    if (!hasWriteInFlight()) {
      queueMicrotask(flushDeferredWork);
    }
  });

  function handleBeforeUnload(event: BeforeUnloadEvent): void {
    if (!hasBlockingWrite()) {
      return;
    }

    event.preventDefault();
    event.returnValue = '';
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', handleBeforeUnload);
  }

  watch(
    () => toValue(options.contextWritePending),
    (pending) => {
      if (pending) {
        pauseRefreshForWrite();

        return;
      }

      flushDeferredWork();
    },
    { flush: 'post' },
  );

  watch(
    () => toValue(options.attachmentTransferPending),
    (pending) => {
      if (!pending) {
        queueMicrotask(flushDeferredWork);
      }
    },
    { flush: 'sync' },
  );

  onUnmounted(() => {
    removeWriteBeforeListener();
    removeWriteStartListener();
    removeWriteFinishListener();
    pendingWriteStarts.clear();
    activeWriteVisits.clear();
    if (typeof window !== 'undefined') {
      window.removeEventListener('beforeunload', handleBeforeUnload);
    }
  });

  return {
    foregroundInteractionBlocked,
    interactionBlocked,
    runNavigation,
    prepareNavigation,
    finishNavigation,
    cancelWaitingNavigation,
    hasWaitingNavigation: () => pendingNavigation !== null,
    navigationInFlight: () =>
      pendingNavigation !== null || activeNavigationCount.value > 0,
    backgroundRequestBlocked,
    beginRefresh,
    trackRefresh,
    finishRefresh,
    cancelRefresh,
    deferReload,
    requestSearchStateWrite,
    deferredSearch: () => deferredSearchValue,
  };
}
