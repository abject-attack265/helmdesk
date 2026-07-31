/**
 * 管理收件箱实例实时订阅，并按当前选择合并列表与会话刷新。
 */
import {
  subscribeReceptionInstance,
  type ReceptionInstancePayload,
} from '@/lib/mercure';
import { onMounted, onUnmounted, toValue, type MaybeRefOrGetter } from 'vue';

const INBOX_RELOAD_DEBOUNCE_MS = 300;
const INBOX_RELOAD_MAX_WAIT_MS = 2_000;
const SELECTION_RELOAD_DEBOUNCE_MS = 150;
const TRANSLATION_RELOAD_DEBOUNCE_MS = 600;
const TRANSLATION_RELOAD_MAX_WAIT_MS = 2_000;

interface UseInboxRealtimeRefreshOptions {
  selectedConversationId: MaybeRefOrGetter<string | null | undefined>;
  selectedContactId: MaybeRefOrGetter<string | null | undefined>;
  reloadListAndCounts: () => void;
  reloadWithSelection: () => void;
  markConversationRead: (conversationId: string) => void | Promise<void>;
  onConversationUnread?: (conversationId: string) => void;
}

/** 建立收件箱唯一实例订阅，并按事件影响范围选择刷新策略。 */
export function useInboxRealtimeRefresh(
  options: UseInboxRealtimeRefreshOptions,
): void {
  let unsubscribe: (() => void) | null = null;
  let inboxReloadTimer: number | null = null;
  let inboxReloadMaxWaitTimer: number | null = null;
  let selectionReloadTimer: number | null = null;
  let translationReloadTimer: number | null = null;
  let translationReloadMaxWaitTimer: number | null = null;
  let subscriptionGeneration = 0;
  let selectedReadRefresh: {
    conversationId: string;
    generation: number;
    trailingReadRequired: boolean;
    promise: Promise<void>;
  } | null = null;
  let mounted = false;
  let disposed = false;

  function clearInboxReloadTimers(): void {
    if (inboxReloadTimer !== null) {
      window.clearTimeout(inboxReloadTimer);
      inboxReloadTimer = null;
    }

    if (inboxReloadMaxWaitTimer !== null) {
      window.clearTimeout(inboxReloadMaxWaitTimer);
      inboxReloadMaxWaitTimer = null;
    }
  }

  function clearTranslationReloadTimer(): void {
    if (translationReloadTimer !== null) {
      window.clearTimeout(translationReloadTimer);
      translationReloadTimer = null;
    }

    if (translationReloadMaxWaitTimer !== null) {
      window.clearTimeout(translationReloadMaxWaitTimer);
      translationReloadMaxWaitTimer = null;
    }
  }

  function clearSelectionReloadTimer(): void {
    if (selectionReloadTimer === null) {
      return;
    }

    window.clearTimeout(selectionReloadTimer);
    selectionReloadTimer = null;
  }

  function clearReloadTimers(): void {
    clearInboxReloadTimers();
    clearSelectionReloadTimer();
    clearTranslationReloadTimer();
  }

  /** 关闭当前实例订阅，并使关联异步回调失效。 */
  function closeSubscription(): void {
    subscriptionGeneration += 1;
    unsubscribe?.();
    unsubscribe = null;
    selectedReadRefresh = null;
  }

  /** 判断事件或异步回调是否仍属于当前实例订阅。 */
  function isCurrentSubscription(generation: number): boolean {
    return mounted && !disposed && subscriptionGeneration === generation;
  }

  function flushWithSelection(): void {
    clearReloadTimers();
    options.reloadWithSelection();
  }

  function flushListAndCounts(): void {
    clearInboxReloadTimers();
    options.reloadListAndCounts();
  }

  function flushTranslationReload(): void {
    clearTranslationReloadTimer();
    options.reloadWithSelection();
  }

  /** 合并普通事件，并保证计数在最大等待时间内刷新。 */
  function scheduleListAndCountsReload(): void {
    if (inboxReloadTimer !== null) {
      window.clearTimeout(inboxReloadTimer);
    }

    inboxReloadTimer = window.setTimeout(
      flushListAndCounts,
      INBOX_RELOAD_DEBOUNCE_MS,
    );

    if (inboxReloadMaxWaitTimer === null) {
      inboxReloadMaxWaitTimer = window.setTimeout(
        flushListAndCounts,
        INBOX_RELOAD_MAX_WAIT_MS,
      );
    }
  }

  /** 合并连续翻译更新，并保证当前选择在最大等待时间内刷新。 */
  function scheduleTranslationReload(): void {
    if (translationReloadTimer !== null) {
      window.clearTimeout(translationReloadTimer);
    }
    translationReloadTimer = window.setTimeout(
      flushTranslationReload,
      TRANSLATION_RELOAD_DEBOUNCE_MS,
    );

    if (translationReloadMaxWaitTimer === null) {
      translationReloadMaxWaitTimer = window.setTimeout(
        flushTranslationReload,
        TRANSLATION_RELOAD_MAX_WAIT_MS,
      );
    }
  }

  /** 合并同一联系人的连续事件后刷新列表与当前选择。 */
  function scheduleWithSelectionReload(): void {
    clearInboxReloadTimers();
    clearSelectionReloadTimer();
    selectionReloadTimer = window.setTimeout(() => {
      selectionReloadTimer = null;
      options.reloadWithSelection();
    }, SELECTION_RELOAD_DEBOUNCE_MS);
  }

  /** 串行标记选中会话已读；每轮结束刷新当前选择，并为期间的新事件补跑一次。 */
  function markSelectedConversationRead(
    conversationId: string,
    generation: number,
  ): void {
    if (
      selectedReadRefresh?.conversationId === conversationId &&
      selectedReadRefresh.generation === generation
    ) {
      selectedReadRefresh.trailingReadRequired = true;
      return;
    }

    const operation = {
      conversationId,
      generation,
      trailingReadRequired: false,
      promise: Promise.resolve(),
    };
    operation.promise = Promise.resolve(
      options.markConversationRead(conversationId),
    ).finally(() => {
      if (selectedReadRefresh !== operation) {
        return;
      }

      selectedReadRefresh = null;
      if (
        !isCurrentSubscription(generation) ||
        toValue(options.selectedConversationId) !== conversationId
      ) {
        return;
      }

      flushWithSelection();
      if (operation.trailingReadRequired) {
        markSelectedConversationRead(conversationId, generation);
      }
    });
    selectedReadRefresh = operation;
    void operation.promise;
  }

  /** 按最新会话与联系人选择处理实例事件。 */
  function handlePayload(
    payload: ReceptionInstancePayload,
    generation: number,
  ): void {
    if (!isCurrentSubscription(generation)) {
      return;
    }

    if (payload.event.endsWith('_translation_updated')) {
      scheduleTranslationReload();
      return;
    }

    const eventConversationId = payload.conversation_id;
    if (
      payload.event === 'visitor_message_created' &&
      eventConversationId &&
      eventConversationId !== toValue(options.selectedConversationId)
    ) {
      options.onConversationUnread?.(eventConversationId);
    }

    if (
      eventConversationId &&
      eventConversationId === toValue(options.selectedConversationId)
    ) {
      markSelectedConversationRead(eventConversationId, generation);
      return;
    }

    const eventContactId = payload.contact_id;
    if (
      eventContactId &&
      eventContactId === toValue(options.selectedContactId)
    ) {
      scheduleWithSelectionReload();
      return;
    }

    scheduleListAndCountsReload();
  }

  /** 订阅当前实例，并关闭上一实例订阅与待执行刷新。 */
  function subscribe(): void {
    closeSubscription();
    clearReloadTimers();

    const generation = subscriptionGeneration;
    console.info('[inbox-realtime] 订阅实例接待事件', {
      instance: 'system',
      generation,
    });

    unsubscribe = subscribeReceptionInstance((payload) => {
      handlePayload(payload, generation);
    });
  }

  function cleanup(): void {
    if (disposed) {
      return;
    }

    disposed = true;
    mounted = false;
    closeSubscription();
    clearReloadTimers();
  }

  onMounted(() => {
    if (disposed) {
      return;
    }

    mounted = true;
    subscribe();
  });

  onUnmounted(cleanup);
}
