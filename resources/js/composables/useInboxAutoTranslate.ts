/**
 * 管理收件箱可见消息的按需翻译、请求状态和译文刷新。
 */
import { localeMatches } from '@/lib/locale';
import { hasTranslatableLetters } from '@/lib/translationText';
import inboxActions from '@/routes/app/inbox';
import type {
  ContactStitchedTimelineData,
  ContactTimelineEntryData,
  InboxSelectionData,
} from '@/types/generated';
import axios from 'axios';
import { type ComputedRef, type Ref, onUnmounted, ref, watch } from 'vue';

const AUTO_TRANSLATE_DEBOUNCE_MS = 500;
const AUTO_TRANSLATE_RETRY_COOLDOWN_MS = 60_000;
const AUTO_TRANSLATE_PENDING_TIMEOUT_MS = 30_000;
const AUTO_TRANSLATE_BATCH_SIZE = 8;

export interface UseInboxAutoTranslateOptions {
  selection: ComputedRef<InboxSelectionData | null>;
  sourceLocale: Ref<string>;
  targetLocale: Ref<string>;
  activeStitchedTimeline: ComputedRef<ContactStitchedTimelineData | null>;
  timelineScrollRef: Ref<HTMLElement | null>;
  enabled: Ref<boolean>;
  /** 非空时仅翻译指定会话。 */
  conversationScopeId: ComputedRef<string | null>;
}

export interface UseInboxAutoTranslateReturn {
  autoTranslatingMessageIds: Ref<Set<string>>;
  translateMessage: (
    conversationId: string,
    messageId: string,
    force: boolean,
  ) => Promise<void>;
  cleanup: () => void;
  scheduleObserverRefresh: () => void;
  stopObserverAndTimers: () => void;
}

export function useInboxAutoTranslate(
  options: UseInboxAutoTranslateOptions,
): UseInboxAutoTranslateReturn {
  const autoTranslatingMessageIds = ref<Set<string>>(new Set());

  // SSR 阶段不注册浏览器观察器、计时器与翻译请求。
  if (typeof window === 'undefined') {
    const noop = (): void => {};

    return {
      autoTranslatingMessageIds,
      translateMessage: async () => undefined,
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

  const visibleTimelineMessageIds = ref<Set<string>>(new Set());
  const autoTranslateQueuedAt = new Map<string, number>();
  const autoTranslatePendingTimers = new Map<string, number>();
  const forcedTranslationBaselines = new Map<string, string>();
  let autoTranslateObserver: IntersectionObserver | null = null;
  let autoTranslateObserveTimer: number | null = null;
  let autoTranslateRequestTimer: number | null = null;
  let autoTranslateRequestController: AbortController | null = null;

  function clearAutoTranslateObserver(): void {
    autoTranslateObserver?.disconnect();
    autoTranslateObserver = null;
  }

  function resetVisibleTimelineState(): void {
    visibleTimelineMessageIds.value = new Set();
  }

  function clearAutoTranslateTimers(): void {
    if (autoTranslateObserveTimer !== null) {
      window.clearTimeout(autoTranslateObserveTimer);
      autoTranslateObserveTimer = null;
    }
    if (autoTranslateRequestTimer !== null) {
      window.clearTimeout(autoTranslateRequestTimer);
      autoTranslateRequestTimer = null;
    }
    autoTranslateRequestController?.abort();
    autoTranslateRequestController = null;
  }

  function stopAutoTranslatePending(messageId: string): void {
    forcedTranslationBaselines.delete(messageId);

    const timer = autoTranslatePendingTimers.get(messageId);
    if (timer !== undefined) {
      window.clearTimeout(timer);
      autoTranslatePendingTimers.delete(messageId);
    }

    if (autoTranslatingMessageIds.value.has(messageId)) {
      const next = new Set(autoTranslatingMessageIds.value);
      next.delete(messageId);
      autoTranslatingMessageIds.value = next;
    }
  }

  function clearAutoTranslatePending(): void {
    autoTranslatePendingTimers.forEach((timer) => window.clearTimeout(timer));
    autoTranslatePendingTimers.clear();
    forcedTranslationBaselines.clear();
    autoTranslatingMessageIds.value = new Set();
  }

  function markAutoTranslatePending(messageIds: string[]): void {
    const next = new Set(autoTranslatingMessageIds.value);

    messageIds.forEach((messageId) => {
      next.add(messageId);

      const existingTimer = autoTranslatePendingTimers.get(messageId);
      if (existingTimer !== undefined) {
        window.clearTimeout(existingTimer);
      }

      autoTranslatePendingTimers.set(
        messageId,
        window.setTimeout(() => {
          console.warn('[inbox-message-translation] 翻译等待超时', {
            messageId,
            timeoutMs: AUTO_TRANSLATE_PENDING_TIMEOUT_MS,
          });
          stopAutoTranslatePending(messageId);
        }, AUTO_TRANSLATE_PENDING_TIMEOUT_MS),
      );
    });

    autoTranslatingMessageIds.value = next;
  }

  function targetTranslation(
    entry: ContactTimelineEntryData,
    localeValue: string,
  ): unknown {
    const payload = entry.payload as {
      translations?: Record<string, unknown>;
    } | null;

    return payload?.translations?.[localeValue] ?? null;
  }

  function messageHasTargetTranslation(
    entry: ContactTimelineEntryData,
    localeValue: string,
  ): boolean {
    const translation = targetTranslation(entry, localeValue) as {
      text?: unknown;
    } | null;

    return (
      typeof translation?.text === 'string' &&
      translation.text.trim().length > 0
    );
  }

  function targetTranslationFingerprint(
    entry: ContactTimelineEntryData,
    localeValue: string,
  ): string {
    return JSON.stringify(targetTranslation(entry, localeValue));
  }

  function messageCanAutoTranslate(entry: ContactTimelineEntryData): boolean {
    const localeValue = targetLocale.value;
    if (
      !selection.value?.can_translate_messages ||
      (conversationScopeId.value !== null &&
        entry.conversation_id !== conversationScopeId.value) ||
      entry.type !== 'message' ||
      entry.kind !== 'text' ||
      !['visitor', 'ai', 'teammate'].includes(String(entry.role)) ||
      typeof entry.content !== 'string' ||
      !hasTranslatableLetters(entry.content) ||
      entry.recalled_at ||
      messageHasTargetTranslation(entry, localeValue)
    ) {
      return false;
    }

    // 未识别语言的消息仍需入队完成检测；同语言消息不会生成译文。
    if (localeMatches(entry.content_locale, localeValue)) {
      return false;
    }

    return true;
  }

  function messageNeedsAutoTranslation(
    entry: ContactTimelineEntryData,
  ): boolean {
    if (!messageCanAutoTranslate(entry)) {
      return false;
    }

    const lastQueuedAt = autoTranslateQueuedAt.get(entry.id) ?? 0;
    return Date.now() - lastQueuedAt >= AUTO_TRANSLATE_RETRY_COOLDOWN_MS;
  }

  function syncAutoTranslatePendingWithTimeline(): void {
    const timeline = activeStitchedTimeline.value;
    if (!timeline || autoTranslatingMessageIds.value.size === 0) {
      return;
    }

    const entriesById = new Map(
      timeline.entries.map((entry) => [entry.id, entry]),
    );
    autoTranslatingMessageIds.value.forEach((messageId) => {
      const entry = entriesById.get(messageId);
      if (!entry) {
        stopAutoTranslatePending(messageId);
        return;
      }

      const forcedBaseline = forcedTranslationBaselines.get(messageId);
      if (forcedBaseline !== undefined) {
        const currentFingerprint = targetTranslationFingerprint(
          entry,
          targetLocale.value,
        );
        if (currentFingerprint !== forcedBaseline) {
          stopAutoTranslatePending(messageId);
        }
        return;
      }

      if (!messageCanAutoTranslate(entry)) {
        stopAutoTranslatePending(messageId);
      }
    });
  }

  function visibleMessagesNeedingTranslation(): Array<{
    id: string;
    conversationId: string;
  }> {
    const timeline = activeStitchedTimeline.value;
    if (
      !enabled.value ||
      !selection.value?.can_translate_messages ||
      !timeline
    ) {
      return [];
    }

    const visibleIds = visibleTimelineMessageIds.value;

    return timeline.entries
      .filter(
        (entry) =>
          visibleIds.has(entry.id) && messageNeedsAutoTranslation(entry),
      )
      .slice(0, AUTO_TRANSLATE_BATCH_SIZE)
      .map((entry) => ({
        id: entry.id,
        conversationId: entry.conversation_id,
      }));
  }

  function scheduleAutoTranslateVisibleMessages(): void {
    if (autoTranslateRequestTimer !== null) {
      window.clearTimeout(autoTranslateRequestTimer);
      autoTranslateRequestTimer = null;
    }

    if (!enabled.value) {
      return;
    }

    autoTranslateRequestTimer = window.setTimeout(() => {
      autoTranslateRequestTimer = null;
      void queueVisibleMessageTranslations();
    }, AUTO_TRANSLATE_DEBOUNCE_MS);
  }

  async function queueVisibleMessageTranslations(): Promise<void> {
    const messages = visibleMessagesNeedingTranslation();
    if (messages.length === 0) {
      return;
    }

    const idsByConversation = new Map<string, string[]>();
    messages.forEach(({ id, conversationId }) => {
      const ids = idsByConversation.get(conversationId) ?? [];
      ids.push(id);
      idsByConversation.set(conversationId, ids);
    });

    const messageIds = messages.map((message) => message.id);
    const queuedAt = Date.now();
    messageIds.forEach((messageId) =>
      autoTranslateQueuedAt.set(messageId, queuedAt),
    );
    markAutoTranslatePending(messageIds);
    autoTranslateRequestController?.abort();
    const controller = new AbortController();
    autoTranslateRequestController = controller;

    try {
      await Promise.all(
        [...idsByConversation].map(([conversationId, ids]) =>
          axios.post(
            inboxActions.conversations.messages.translate.url({
              conversation: conversationId,
            }),
            {
              message_ids: ids,
              source_locale: sourceLocale.value,
              target_locale: targetLocale.value,
            },
            { signal: controller.signal },
          ),
        ),
      );
      if (messageIds.length === AUTO_TRANSLATE_BATCH_SIZE) {
        scheduleAutoTranslateVisibleMessages();
      }
    } catch (error) {
      if (!controller.signal.aborted) {
        console.warn('[inbox-message-translation] 可见消息翻译入队失败', {
          messageIds,
          sourceLocale: sourceLocale.value,
          targetLocale: targetLocale.value,
          error,
        });
        messageIds.forEach((messageId) =>
          autoTranslateQueuedAt.delete(messageId),
        );
        messageIds.forEach((messageId) => stopAutoTranslatePending(messageId));
      }
    } finally {
      if (autoTranslateRequestController === controller) {
        autoTranslateRequestController = null;
      }
    }
  }

  function refreshAutoTranslateObserver(): void {
    clearAutoTranslateObserver();

    if (
      typeof window === 'undefined' ||
      !enabled.value ||
      !selection.value?.can_translate_messages ||
      !timelineScrollRef.value
    ) {
      return;
    }

    autoTranslateObserver = new IntersectionObserver(
      (entries) => {
        const next = new Set(visibleTimelineMessageIds.value);
        entries.forEach((entry) => {
          const messageId = entry.target.getAttribute(
            'data-inbox-timeline-message-id',
          );
          if (!messageId) {
            return;
          }

          if (entry.isIntersecting) {
            next.add(messageId);
          } else {
            next.delete(messageId);
          }
        });
        visibleTimelineMessageIds.value = next;
        scheduleAutoTranslateVisibleMessages();
      },
      {
        root: timelineScrollRef.value,
        rootMargin: '160px 0px',
        threshold: 0.1,
      },
    );

    timelineScrollRef.value
      .querySelectorAll<HTMLElement>('[data-inbox-timeline-message-id]')
      .forEach((element) => autoTranslateObserver?.observe(element));
  }

  function scheduleAutoTranslateObserverRefresh(): void {
    if (autoTranslateObserveTimer !== null) {
      window.clearTimeout(autoTranslateObserveTimer);
    }

    autoTranslateObserveTimer = window.setTimeout(() => {
      autoTranslateObserveTimer = null;
      refreshAutoTranslateObserver();
    }, 0);
  }

  watch(
    () => [
      selection.value?.conversation.id,
      selection.value?.can_translate_messages,
      conversationScopeId.value,
      activeStitchedTimeline.value?.entries.map((entry) => entry.id).join('|'),
    ],
    (current, previous) => {
      if (previous !== undefined && current[0] !== previous[0]) {
        resetVisibleTimelineState();
      }
      scheduleAutoTranslateObserverRefresh();
    },
    { immediate: true, flush: 'post' },
  );

  // 目标语言或时间线译文变化时同步等待状态。
  watch(
    () => [
      targetLocale.value,
      activeStitchedTimeline.value?.entries
        .map((entry) =>
          [
            entry.content_locale ?? '',
            targetTranslationFingerprint(entry, targetLocale.value),
          ].join(':'),
        )
        .join('|'),
    ],
    () => {
      syncAutoTranslatePendingWithTimeline();
      scheduleAutoTranslateVisibleMessages();
    },
    { immediate: true, flush: 'post' },
  );

  async function translateMessage(
    conversationId: string,
    messageId: string,
    force: boolean,
  ): Promise<void> {
    if (force) {
      const entry = activeStitchedTimeline.value?.entries.find(
        (timelineEntry) => timelineEntry.id === messageId,
      );
      if (!entry) {
        throw new Error(`找不到待重新翻译的消息：${messageId}`);
      }
      forcedTranslationBaselines.set(
        messageId,
        targetTranslationFingerprint(entry, targetLocale.value),
      );
    }

    markAutoTranslatePending([messageId]);
    try {
      await axios.post(
        inboxActions.conversations.messages.translate.url({
          conversation: conversationId,
        }),
        {
          message_ids: [messageId],
          force,
          source_locale: sourceLocale.value,
          target_locale: targetLocale.value,
        },
      );
    } catch (error) {
      console.warn('[inbox-message-translation] 单条消息翻译入队失败', {
        conversationId,
        messageId,
        force,
        sourceLocale: sourceLocale.value,
        targetLocale: targetLocale.value,
        error,
      });
      stopAutoTranslatePending(messageId);
    }
  }

  function cleanup(): void {
    clearAutoTranslateObserver();
    clearAutoTranslateTimers();
    clearAutoTranslatePending();
    resetVisibleTimelineState();
  }

  onUnmounted(cleanup);

  return {
    autoTranslatingMessageIds,
    translateMessage,
    cleanup,
    scheduleObserverRefresh: scheduleAutoTranslateObserverRefresh,
    stopObserverAndTimers: () => {
      clearAutoTranslateObserver();
      clearAutoTranslateTimers();
      resetVisibleTimelineState();
    },
  };
}
