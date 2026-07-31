/**
 * 管理收件箱联系人时间线的本地窗口、游标加载、消息锚定与滚动位置。
 */
import { useI18n } from '@/composables/useI18n';
import { useToast } from '@/composables/useToast';
import inboxActions from '@/routes/app/inbox';
import type {
  ContactStitchedTimelineData,
  ContactTimelineEntryData,
  InboxSelectionData,
} from '@/types/generated';
import {
  type ComputedRef,
  type Ref,
  type WatchStopHandle,
  computed,
  nextTick,
  onScopeDispose,
  ref,
  watch,
} from 'vue';

const TIMELINE_PAGE_SIZE = 50;
const TIMELINE_SCROLL_EDGE_PX = 96;
const TIMELINE_MEDIA_FOLLOW_THRESHOLD_PX = 480;
const TIMELINE_HIGHLIGHT_DURATION_MS = 2400;
const TIMELINE_ANCHOR_RECHECK_DELAY_MS = 120;
const TIMELINE_AUTO_LOAD_RESUME_DELAY_MS = 700;

interface TimelineWindowQuery {
  before?: string;
  after?: string;
  anchor_type?: 'message';
  anchor_id?: string;
}

type TimelineWindowDirection = 'before' | 'after';

interface UseInboxTimelineWindowOptions {
  selection: ComputedRef<InboxSelectionData | null>;
  pendingReplyUploadCount: ComputedRef<number>;
  isAiReplying: ComputedRef<boolean>;
}

interface UseInboxTimelineWindowReturn {
  timelineScrollRef: Ref<HTMLElement | null>;
  activeStitchedTimeline: ComputedRef<ContactStitchedTimelineData | null>;
  highlightedTimelineMessageId: Ref<string | null>;
  timelineLoadingPrevious: Ref<boolean>;
  timelineLoadingNext: Ref<boolean>;
  anchorTimelineToMessage: (messageId: string) => Promise<void>;
  handleTimelineScroll: () => void;
  handleTimelineMediaLoad: (event: Event) => void;
  scrollTimelineToBottom: () => Promise<void>;
}

/**
 * 为当前联系人维护可双向扩展的消息时间线窗口。
 */
export function useInboxTimelineWindow(
  options: UseInboxTimelineWindowOptions,
): UseInboxTimelineWindowReturn {
  const { selection, pendingReplyUploadCount, isAiReplying } = options;
  const { t } = useI18n();
  const { toast } = useToast();

  const timelineScrollRef = ref<HTMLElement | null>(null);
  const stitchedTimeline = ref<ContactStitchedTimelineData | null>(
    selection.value?.stitched_timeline ?? null,
  );
  const activeStitchedTimeline = computed(() => stitchedTimeline.value);
  const highlightedTimelineMessageId = ref<string | null>(null);
  const timelineLoadingPrevious = ref(false);
  const timelineLoadingNext = ref(false);
  const timelineLoadingAnchor = ref(false);
  const timelineAutoLoadPaused = ref(false);

  let highlightedTimelineMessageTimer: number | null = null;
  let timelineAutoLoadResumeTimer: number | null = null;
  let timelineAnchorScrollTimer: number | null = null;
  let timelineAnchorScrollFrame: number | null = null;
  let timelineBottomScrollFrame: number | null = null;
  let timelineRequestController: AbortController | null = null;
  let timelineAnchorLoadSequence = 0;
  let timelineStateVersion = 0;
  let disposed = false;
  const watchStops: WatchStopHandle[] = [];

  function clearTimelineMessageHighlight(): void {
    if (highlightedTimelineMessageTimer !== null) {
      window.clearTimeout(highlightedTimelineMessageTimer);
      highlightedTimelineMessageTimer = null;
    }

    highlightedTimelineMessageId.value = null;
  }

  function clearTimelineAnchorScrollTasks(): void {
    if (timelineAutoLoadResumeTimer !== null) {
      window.clearTimeout(timelineAutoLoadResumeTimer);
      timelineAutoLoadResumeTimer = null;
    }

    if (timelineAnchorScrollTimer !== null) {
      window.clearTimeout(timelineAnchorScrollTimer);
      timelineAnchorScrollTimer = null;
    }

    if (timelineAnchorScrollFrame !== null) {
      window.cancelAnimationFrame(timelineAnchorScrollFrame);
      timelineAnchorScrollFrame = null;
    }
  }

  function clearTimelineBottomScrollTask(): void {
    if (timelineBottomScrollFrame === null) {
      return;
    }

    window.cancelAnimationFrame(timelineBottomScrollFrame);
    timelineBottomScrollFrame = null;
  }

  function abortTimelineRequest(): void {
    timelineRequestController?.abort();
    timelineRequestController = null;
  }

  function isTimelineRequestCancellation(error: unknown): boolean {
    return (
      typeof error === 'object' &&
      error !== null &&
      'name' in error &&
      error.name === 'AbortError'
    );
  }

  function createTimelineRequestCancellation(): Error {
    const error = new Error('收件箱时间线请求已失效');
    error.name = 'AbortError';

    return error;
  }

  /** 写入本地时间线，并使进行中的窗口合并失效。 */
  function replaceStitchedTimeline(
    timeline: ContactStitchedTimelineData | null,
  ): void {
    timelineStateVersion += 1;
    stitchedTimeline.value = timeline;
  }

  function findTimelineMessageElement(messageId: string): HTMLElement | null {
    const timeline = timelineScrollRef.value;
    if (!timeline) {
      return null;
    }

    const elements = timeline.querySelectorAll<HTMLElement>(
      '[data-inbox-timeline-message-id]',
    );

    return (
      Array.from(elements).find(
        (element) => element.dataset.inboxTimelineMessageId === messageId,
      ) ?? null
    );
  }

  function centerTimelineMessage(messageId: string): boolean {
    const timeline = timelineScrollRef.value;
    const target = findTimelineMessageElement(messageId);

    if (!timeline || !target) {
      return false;
    }

    const timelineRect = timeline.getBoundingClientRect();
    const targetRect = target.getBoundingClientRect();
    const targetTop = targetRect.top - timelineRect.top + timeline.scrollTop;
    const nextScrollTop =
      targetTop - (timeline.clientHeight - targetRect.height) / 2;

    timeline.scrollTo({
      top: Math.max(0, nextScrollTop),
      behavior: 'auto',
    });

    return true;
  }

  /** 等待锚点窗口渲染后定位目标消息，并暂停边缘自动加载。 */
  async function focusTimelineMessage(messageId: string): Promise<void> {
    clearTimelineAnchorScrollTasks();
    clearTimelineBottomScrollTask();
    timelineAutoLoadPaused.value = true;

    await nextTick();
    if (disposed) {
      return;
    }

    timelineAnchorScrollFrame = window.requestAnimationFrame(() => {
      timelineAnchorScrollFrame = null;
      centerTimelineMessage(messageId);
    });

    timelineAnchorScrollTimer = window.setTimeout(() => {
      const positioned = centerTimelineMessage(messageId);
      timelineAnchorScrollTimer = null;

      if (!positioned && !disposed) {
        console.warn('[inbox-timeline] 消息锚点定位失败', {
          instance: 'system',
          conversationId: selection.value?.conversation.id,
          contactId: selection.value?.contact.id,
          messageId,
        });
      }
    }, TIMELINE_ANCHOR_RECHECK_DELAY_MS);

    timelineAutoLoadResumeTimer = window.setTimeout(() => {
      timelineAutoLoadPaused.value = false;
      timelineAutoLoadResumeTimer = null;
    }, TIMELINE_AUTO_LOAD_RESUME_DELAY_MS);
  }

  /** 校验时间线响应的外层结构，缺字段时立即失败而不是把 undefined 带进窗口合并。 */
  function parseTimelineWindowResponse(
    payload: unknown,
  ): ContactStitchedTimelineData {
    const timeline =
      typeof payload === 'object' && payload !== null && 'timeline' in payload
        ? payload.timeline
        : null;
    if (
      typeof timeline !== 'object' ||
      timeline === null ||
      !('contact_id' in timeline) ||
      typeof timeline.contact_id !== 'string' ||
      !('entries' in timeline) ||
      !Array.isArray(timeline.entries)
    ) {
      throw new Error('收件箱时间线响应格式无效');
    }

    return timeline as ContactStitchedTimelineData;
  }

  async function fetchContactTimelineWindow(
    query: TimelineWindowQuery,
  ): Promise<ContactStitchedTimelineData> {
    const contactId = selection.value?.contact.id;

    if (!contactId) {
      throw new Error('收件箱时间线缺少已选联系人');
    }

    abortTimelineRequest();
    const controller = new AbortController();
    timelineRequestController = controller;

    try {
      const response = await fetch(
        inboxActions.contacts.timeline.url(
          { contactId },
          { query: { ...query, per_page: TIMELINE_PAGE_SIZE } },
        ),
        {
          signal: controller.signal,
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        },
      );

      if (!response.ok) {
        throw new Error(`收件箱时间线请求失败：${response.status}`);
      }

      const data: unknown = await response.json();
      if (
        controller.signal.aborted ||
        selection.value?.contact.id !== contactId
      ) {
        throw createTimelineRequestCancellation();
      }

      const timeline = parseTimelineWindowResponse(data);
      if (timeline.contact_id !== contactId) {
        throw new Error('收件箱时间线响应与已选联系人不一致');
      }

      return timeline;
    } finally {
      if (timelineRequestController === controller) {
        timelineRequestController = null;
      }
    }
  }

  function reportTimelineLoadFailure(
    error: unknown,
    context: Record<string, string>,
  ): void {
    console.warn('[inbox-timeline] 联系人时间线加载失败', {
      instance: 'system',
      conversationId: selection.value?.conversation.id,
      contactId: selection.value?.contact.id,
      ...context,
      errorType: error instanceof Error ? error.name : typeof error,
    });
    toast.error(t('聊天记录加载失败，请稍后重试'));
  }

  function timelineEntryKey(entry: ContactTimelineEntryData): string {
    return `${entry.type}:${entry.id}`;
  }

  /** 按条目键去重窗口，并沿加载方向更新对应游标。 */
  function mergeTimelineWindow(
    current: ContactStitchedTimelineData,
    incoming: ContactStitchedTimelineData,
    direction: TimelineWindowDirection,
  ): ContactStitchedTimelineData {
    const existingKeys = new Set(current.entries.map(timelineEntryKey));
    const incomingEntries = incoming.entries.filter(
      (entry) => !existingKeys.has(timelineEntryKey(entry)),
    );
    const conversationSequenceById = {
      ...current.conversation_sequence_by_id,
      ...incoming.conversation_sequence_by_id,
    };
    const conversationsById = new Map(
      [...current.conversations, ...incoming.conversations].map(
        (conversation) => [conversation.id, conversation] as const,
      ),
    );
    const conversations = Array.from(conversationsById.values()).sort(
      (left, right) => {
        const leftSequence = conversationSequenceById[left.id];
        const rightSequence = conversationSequenceById[right.id];

        if (leftSequence === undefined || rightSequence === undefined) {
          throw new Error('收件箱时间线会话缺少联系人会话序号');
        }

        return leftSequence - rightSequence;
      },
    );

    return {
      ...current,
      conversations,
      conversation_sequence_by_id: conversationSequenceById,
      entries:
        direction === 'before'
          ? [...incomingEntries, ...current.entries]
          : [...current.entries, ...incomingEntries],
      previous_cursor:
        direction === 'before'
          ? incoming.previous_cursor
          : current.previous_cursor,
      next_cursor:
        direction === 'after' ? incoming.next_cursor : current.next_cursor,
      anchor_entry_id: incoming.anchor_entry_id ?? current.anchor_entry_id,
    };
  }

  /** 加载并拼接时间线中更早的条目，同时保持当前阅读位置。 */
  async function loadPreviousTimelineEntries(): Promise<void> {
    const timeline = stitchedTimeline.value;

    if (
      disposed ||
      timelineLoadingPrevious.value ||
      timelineLoadingNext.value ||
      timelineLoadingAnchor.value ||
      timelineAutoLoadPaused.value ||
      !timeline?.previous_cursor
    ) {
      return;
    }

    const stateVersion = timelineStateVersion;
    const scrollElement = timelineScrollRef.value;
    const previousScrollHeight = scrollElement?.scrollHeight ?? 0;

    timelineLoadingPrevious.value = true;
    try {
      const incoming = await fetchContactTimelineWindow({
        before: timeline.previous_cursor,
      });
      if (stateVersion !== timelineStateVersion || disposed) {
        return;
      }

      replaceStitchedTimeline(
        mergeTimelineWindow(timeline, incoming, 'before'),
      );
    } catch (error) {
      if (!isTimelineRequestCancellation(error)) {
        reportTimelineLoadFailure(error, { direction: 'before' });
      }
      return;
    } finally {
      timelineLoadingPrevious.value = false;
    }

    await nextTick();
    if (disposed) {
      return;
    }

    if (scrollElement && timelineScrollRef.value === scrollElement) {
      scrollElement.scrollTop +=
        scrollElement.scrollHeight - previousScrollHeight;
    }
  }

  async function loadNextTimelineEntries(): Promise<void> {
    const timeline = stitchedTimeline.value;

    if (
      disposed ||
      timelineLoadingPrevious.value ||
      timelineLoadingNext.value ||
      timelineLoadingAnchor.value ||
      timelineAutoLoadPaused.value ||
      !timeline?.next_cursor
    ) {
      return;
    }

    const stateVersion = timelineStateVersion;
    timelineLoadingNext.value = true;
    try {
      const incoming = await fetchContactTimelineWindow({
        after: timeline.next_cursor,
      });
      if (stateVersion !== timelineStateVersion || disposed) {
        return;
      }

      replaceStitchedTimeline(mergeTimelineWindow(timeline, incoming, 'after'));
    } catch (error) {
      if (!isTimelineRequestCancellation(error)) {
        reportTimelineLoadFailure(error, { direction: 'after' });
      }
    } finally {
      timelineLoadingNext.value = false;
    }
  }

  function handleTimelineScroll(): void {
    const scrollElement = timelineScrollRef.value;

    if (!scrollElement || timelineAutoLoadPaused.value) {
      return;
    }

    if (scrollElement.scrollTop <= TIMELINE_SCROLL_EDGE_PX) {
      void loadPreviousTimelineEntries();
    }

    const bottomDistance =
      scrollElement.scrollHeight -
      scrollElement.scrollTop -
      scrollElement.clientHeight;

    if (bottomDistance <= TIMELINE_SCROLL_EDGE_PX) {
      void loadNextTimelineEntries();
    }
  }

  function isTimelineNearBottom(threshold = TIMELINE_SCROLL_EDGE_PX): boolean {
    const element = timelineScrollRef.value;

    if (!element) {
      return false;
    }

    return (
      element.scrollHeight - element.scrollTop - element.clientHeight <=
      threshold
    );
  }

  async function scrollTimelineToBottom(): Promise<void> {
    if (typeof window === 'undefined' || disposed) {
      return;
    }

    await nextTick();
    if (disposed) {
      return;
    }

    const element = timelineScrollRef.value;
    if (!element) {
      return;
    }

    element.scrollTop = element.scrollHeight;
    clearTimelineBottomScrollTask();
    timelineBottomScrollFrame = window.requestAnimationFrame(() => {
      timelineBottomScrollFrame = null;
      const current = timelineScrollRef.value;
      if (current) {
        current.scrollTop = current.scrollHeight;
      }
    });
  }

  /** 在底部附近加载图片后维持贴底状态。 */
  function handleTimelineMediaLoad(event: Event): void {
    if (
      typeof HTMLImageElement !== 'undefined' &&
      event.target instanceof HTMLImageElement &&
      event.target.dataset.messageAttachmentImage === 'true' &&
      activeStitchedTimeline.value?.next_cursor === null &&
      isTimelineNearBottom(TIMELINE_MEDIA_FOLLOW_THRESHOLD_PX)
    ) {
      void scrollTimelineToBottom();
    }
  }

  async function anchorTimelineToMessage(messageId: string): Promise<void> {
    const loadSequence = ++timelineAnchorLoadSequence;
    const stateVersion = timelineStateVersion;
    timelineLoadingAnchor.value = true;

    try {
      const timeline = await fetchContactTimelineWindow({
        anchor_type: 'message',
        anchor_id: messageId,
      });
      if (
        loadSequence !== timelineAnchorLoadSequence ||
        stateVersion !== timelineStateVersion ||
        disposed
      ) {
        return;
      }

      replaceStitchedTimeline(timeline);
    } catch (error) {
      if (!isTimelineRequestCancellation(error)) {
        reportTimelineLoadFailure(error, { messageId });
      }
      return;
    } finally {
      if (loadSequence === timelineAnchorLoadSequence) {
        timelineLoadingAnchor.value = false;
      }
    }

    highlightedTimelineMessageId.value = messageId;
    await focusTimelineMessage(messageId);
    if (disposed) {
      return;
    }

    if (highlightedTimelineMessageTimer !== null) {
      window.clearTimeout(highlightedTimelineMessageTimer);
    }
    highlightedTimelineMessageTimer = window.setTimeout(() => {
      highlightedTimelineMessageId.value = null;
      highlightedTimelineMessageTimer = null;
    }, TIMELINE_HIGHLIGHT_DURATION_MS);
  }

  watchStops.push(
    watch(
      () => selection.value?.stitched_timeline,
      (timeline) => {
        abortTimelineRequest();
        timelineAnchorLoadSequence += 1;
        timelineLoadingPrevious.value = false;
        timelineLoadingNext.value = false;
        timelineLoadingAnchor.value = false;
        replaceStitchedTimeline(timeline ?? null);
      },
      { immediate: true },
    ),
  );

  watchStops.push(
    watch(
      () => selection.value?.conversation.id,
      () => {
        clearTimelineMessageHighlight();
        clearTimelineAnchorScrollTasks();
        timelineAutoLoadPaused.value = false;
      },
      { immediate: true },
    ),
  );

  watchStops.push(
    watch(
      () =>
        [
          selection.value?.conversation.id,
          selection.value?.stitched_timeline.entries.length,
          pendingReplyUploadCount.value,
          isAiReplying.value,
        ] as const,
      (current, previous) => {
        if (!selection.value) {
          return;
        }

        const conversationChanged = !previous || current[0] !== previous[0];
        if (conversationChanged || isTimelineNearBottom()) {
          void scrollTimelineToBottom();
        }
      },
      { immediate: true, flush: 'pre' },
    ),
  );

  function cleanup(): void {
    if (disposed) {
      return;
    }

    disposed = true;
    watchStops.forEach((stop) => stop());
    clearTimelineMessageHighlight();
    clearTimelineAnchorScrollTasks();
    clearTimelineBottomScrollTask();
    abortTimelineRequest();
    timelineAnchorLoadSequence += 1;
    timelineAutoLoadPaused.value = false;
    timelineLoadingPrevious.value = false;
    timelineLoadingNext.value = false;
    timelineLoadingAnchor.value = false;
  }

  onScopeDispose(cleanup);

  return {
    timelineScrollRef,
    activeStitchedTimeline,
    highlightedTimelineMessageId,
    timelineLoadingPrevious,
    timelineLoadingNext,
    anchorTimelineToMessage,
    handleTimelineScroll,
    handleTimelineMediaLoad,
    scrollTimelineToBottom,
  };
}
