/**
 * 管理收件箱可见会话摘要的按需翻译和请求状态。
 */
import { localeMatches } from '@/lib/locale';
import inboxActions from '@/routes/app/inbox';
import type {
  ContactStitchedTimelineData,
  ConversationSummaryData,
  InboxSelectionData,
} from '@/types/generated';
import axios from 'axios';
import { type ComputedRef, type Ref, onUnmounted, ref, watch } from 'vue';

const AUTO_TRANSLATE_DEBOUNCE_MS = 500;
const AUTO_TRANSLATE_RETRY_COOLDOWN_MS = 60_000;
const AUTO_TRANSLATE_PENDING_TIMEOUT_MS = 30_000;
const AUTO_TRANSLATE_BATCH_SIZE = 4;

export interface UseInboxSummaryAutoTranslateOptions {
  selection: ComputedRef<InboxSelectionData | null>;
  sourceLocale: Ref<string>;
  targetLocale: Ref<string>;
  activeStitchedTimeline: ComputedRef<ContactStitchedTimelineData | null>;
  timelineScrollRef: Ref<HTMLElement | null>;
  enabled: Ref<boolean>;
  conversationScopeId: ComputedRef<string | null>;
}

export interface UseInboxSummaryAutoTranslateReturn {
  autoTranslatingSummaryIds: Ref<Set<string>>;
  translateSummary: (conversationId: string, force: boolean) => Promise<void>;
  cleanup: () => void;
  scheduleObserverRefresh: () => void;
  stopObserverAndTimers: () => void;
}

export function useInboxSummaryAutoTranslate(
  options: UseInboxSummaryAutoTranslateOptions,
): UseInboxSummaryAutoTranslateReturn {
  const autoTranslatingSummaryIds = ref<Set<string>>(new Set());

  // SSR 阶段不注册浏览器观察器、计时器与翻译请求。
  if (typeof window === 'undefined') {
    const noop = (): void => {};

    return {
      autoTranslatingSummaryIds,
      translateSummary: async () => undefined,
      cleanup: noop,
      scheduleObserverRefresh: noop,
      stopObserverAndTimers: noop,
    };
  }

  const {
    selection,
    sourceLocale,
    targetLocale,
    activeStitchedTimeline,
    timelineScrollRef,
    enabled,
    conversationScopeId,
  } = options;

  const visibleSummaryIds = ref<Set<string>>(new Set());
  const queuedAt = new Map<string, number>();
  const pendingTimers = new Map<string, number>();
  let observer: IntersectionObserver | null = null;
  let observeTimer: number | null = null;
  let requestTimer: number | null = null;
  let requestController: AbortController | null = null;

  function clearObserver(): void {
    observer?.disconnect();
    observer = null;
    visibleSummaryIds.value = new Set();
  }

  function clearTimers(): void {
    if (observeTimer !== null) {
      window.clearTimeout(observeTimer);
      observeTimer = null;
    }
    if (requestTimer !== null) {
      window.clearTimeout(requestTimer);
      requestTimer = null;
    }
    requestController?.abort();
    requestController = null;
  }

  function stopPending(conversationId: string): void {
    const timer = pendingTimers.get(conversationId);
    if (timer !== undefined) {
      window.clearTimeout(timer);
      pendingTimers.delete(conversationId);
    }

    if (autoTranslatingSummaryIds.value.has(conversationId)) {
      const next = new Set(autoTranslatingSummaryIds.value);
      next.delete(conversationId);
      autoTranslatingSummaryIds.value = next;
    }
  }

  function clearPending(): void {
    pendingTimers.forEach((timer) => window.clearTimeout(timer));
    pendingTimers.clear();
    autoTranslatingSummaryIds.value = new Set();
  }

  function markPending(conversationIds: string[]): void {
    const next = new Set(autoTranslatingSummaryIds.value);

    conversationIds.forEach((conversationId) => {
      next.add(conversationId);

      const timer = pendingTimers.get(conversationId);
      if (timer !== undefined) {
        window.clearTimeout(timer);
      }

      pendingTimers.set(
        conversationId,
        window.setTimeout(() => {
          console.warn('[inbox-summary-translation] 翻译等待超时', {
            conversationId,
            timeoutMs: AUTO_TRANSLATE_PENDING_TIMEOUT_MS,
          });
          stopPending(conversationId);
        }, AUTO_TRANSLATE_PENDING_TIMEOUT_MS),
      );
    });

    autoTranslatingSummaryIds.value = next;
  }

  function hasTargetTranslation(
    conversation: ConversationSummaryData,
  ): boolean {
    const text = conversation.summary_translations?.[targetLocale.value]?.text;

    return typeof text === 'string' && text.trim().length > 0;
  }

  function targetTranslationFingerprint(
    conversation: ConversationSummaryData,
  ): string {
    return JSON.stringify(
      conversation.summary_translations?.[targetLocale.value] ?? null,
    );
  }

  function summaryCanAutoTranslate(
    conversation: ConversationSummaryData,
  ): boolean {
    if (
      !selection.value?.can_translate_messages ||
      (conversationScopeId.value !== null &&
        conversation.id !== conversationScopeId.value) ||
      !conversation.summary ||
      conversation.summary.trim() === '' ||
      hasTargetTranslation(conversation)
    ) {
      return false;
    }

    // 语言未知的摘要仍需请求翻译服务完成源语言识别。
    if (localeMatches(conversation.summary_locale, targetLocale.value)) {
      return false;
    }

    return true;
  }

  function summaryNeedsAutoTranslation(
    conversation: ConversationSummaryData,
  ): boolean {
    if (!summaryCanAutoTranslate(conversation)) {
      return false;
    }

    const lastQueuedAt = queuedAt.get(conversation.id) ?? 0;
    return Date.now() - lastQueuedAt >= AUTO_TRANSLATE_RETRY_COOLDOWN_MS;
  }

  function allConversations(): ConversationSummaryData[] {
    const map = new Map<string, ConversationSummaryData>();
    const selected = selection.value?.conversation;
    if (selected) {
      map.set(selected.id, selected);
    }
    activeStitchedTimeline.value?.conversations.forEach((conversation) => {
      map.set(conversation.id, conversation);
    });

    return Array.from(map.values());
  }

  function syncPendingWithConversations(): void {
    if (autoTranslatingSummaryIds.value.size === 0) {
      return;
    }

    const conversations = new Map(
      allConversations().map((conversation) => [conversation.id, conversation]),
    );
    autoTranslatingSummaryIds.value.forEach((conversationId) => {
      const conversation = conversations.get(conversationId);
      if (!conversation || !summaryCanAutoTranslate(conversation)) {
        stopPending(conversationId);
      }
    });
  }

  function visibleIdsNeedingTranslation(): string[] {
    if (
      !enabled.value ||
      !selection.value?.can_translate_messages ||
      !activeStitchedTimeline.value
    ) {
      return [];
    }

    const visibleIds = visibleSummaryIds.value;

    return allConversations()
      .filter(
        (conversation) =>
          visibleIds.has(conversation.id) &&
          summaryNeedsAutoTranslation(conversation),
      )
      .map((conversation) => conversation.id)
      .slice(0, AUTO_TRANSLATE_BATCH_SIZE);
  }

  function scheduleVisibleSummaryTranslations(): void {
    if (requestTimer !== null) {
      window.clearTimeout(requestTimer);
      requestTimer = null;
    }

    if (!enabled.value) {
      return;
    }

    requestTimer = window.setTimeout(() => {
      requestTimer = null;
      void queueVisibleSummaryTranslations();
    }, AUTO_TRANSLATE_DEBOUNCE_MS);
  }

  async function queueVisibleSummaryTranslations(): Promise<void> {
    const conversation = selection.value?.conversation;
    const conversationIds = visibleIdsNeedingTranslation();
    if (!conversation || conversationIds.length === 0) {
      return;
    }

    const now = Date.now();
    conversationIds.forEach((conversationId) =>
      queuedAt.set(conversationId, now),
    );
    markPending(conversationIds);
    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;

    try {
      await axios.post(
        inboxActions.conversations.summaries.translate.url({
          conversation: conversation.id,
        }),
        {
          conversation_ids: conversationIds,
          source_locale: sourceLocale.value,
          target_locale: targetLocale.value,
        },
        { signal: controller.signal },
      );
    } catch (error) {
      if (!controller.signal.aborted) {
        console.warn('[inbox-summary-translation] 可见摘要翻译入队失败', {
          conversationIds,
          sourceLocale: sourceLocale.value,
          targetLocale: targetLocale.value,
          error,
        });
        conversationIds.forEach((conversationId) =>
          queuedAt.delete(conversationId),
        );
        conversationIds.forEach((conversationId) =>
          stopPending(conversationId),
        );
      }
    } finally {
      if (requestController === controller) {
        requestController = null;
      }
    }
  }

  function refreshObserver(): void {
    clearObserver();

    if (
      typeof window === 'undefined' ||
      !enabled.value ||
      !selection.value?.can_translate_messages ||
      !timelineScrollRef.value
    ) {
      return;
    }

    observer = new IntersectionObserver(
      (entries) => {
        const next = new Set(visibleSummaryIds.value);
        entries.forEach((entry) => {
          const conversationId = entry.target.getAttribute(
            'data-inbox-conversation-summary-id',
          );
          if (!conversationId) {
            return;
          }

          if (entry.isIntersecting) {
            next.add(conversationId);
          } else {
            next.delete(conversationId);
          }
        });
        visibleSummaryIds.value = next;
        scheduleVisibleSummaryTranslations();
      },
      {
        root: timelineScrollRef.value,
        rootMargin: '160px 0px',
        threshold: 0.1,
      },
    );

    timelineScrollRef.value
      .querySelectorAll<HTMLElement>('[data-inbox-conversation-summary-id]')
      .forEach((element) => observer?.observe(element));
  }

  function scheduleObserverRefresh(): void {
    if (observeTimer !== null) {
      window.clearTimeout(observeTimer);
    }

    observeTimer = window.setTimeout(() => {
      observeTimer = null;
      refreshObserver();
    }, 0);
  }

  watch(
    () => [
      selection.value?.conversation.id,
      selection.value?.can_translate_messages,
      conversationScopeId.value,
      targetLocale.value,
      allConversations()
        .map((conversation) =>
          [
            conversation.id,
            conversation.summary ?? '',
            conversation.summary_locale ?? '',
            targetTranslationFingerprint(conversation),
          ].join(':'),
        )
        .join('|'),
    ],
    () => {
      syncPendingWithConversations();
      scheduleObserverRefresh();
    },
    { immediate: true, flush: 'post' },
  );

  async function translateSummary(
    conversationId: string,
    force: boolean,
  ): Promise<void> {
    markPending([conversationId]);
    try {
      await axios.post(
        inboxActions.conversations.summaries.translate.url({
          conversation: conversationId,
        }),
        {
          conversation_ids: [conversationId],
          force,
          source_locale: sourceLocale.value,
          target_locale: targetLocale.value,
        },
      );
    } catch (error) {
      console.warn('[inbox-summary-translation] 摘要翻译入队失败', {
        conversationId,
        force,
        sourceLocale: sourceLocale.value,
        targetLocale: targetLocale.value,
        error,
      });
      stopPending(conversationId);
    }
  }

  function cleanup(): void {
    clearObserver();
    clearTimers();
    clearPending();
  }

  onUnmounted(cleanup);

  return {
    autoTranslatingSummaryIds,
    translateSummary,
    cleanup,
    scheduleObserverRefresh,
    stopObserverAndTimers: () => {
      clearObserver();
      clearTimers();
      clearPending();
    },
  };
}
