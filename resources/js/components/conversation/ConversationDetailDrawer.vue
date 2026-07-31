<!--
  会话详情抽屉，展示聊天记录、处理记录和会话相关操作。
-->
<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import { getAvatarInitial } from '@/lib/initials';
import app from '@/routes/app';
import type {
  ConversationDetailData,
  ListConversationItemData,
  TimelineEntryData,
} from '@/types/generated';
import { ArrowUpRight } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

import ConversationEventLine from './ConversationEventLine.vue';
import ConversationMessageBubble from './ConversationMessageBubble.vue';

const props = defineProps<{
  conversationId: string;
  fallbackConversation?: ListConversationItemData | null;
}>();

const emit = defineEmits<{
  viewContact: [contactId: string];
}>();

const { t } = useI18n();
const { formatDateTime } = useDateTime();
const { formatVisitorName } = useVisitorDisplay();

const loading = ref(false);
const loadingMore = ref(false);
const detail = ref<ConversationDetailData | null>(null);

type TimelineFilter = 'all' | 'messages' | 'events';

const TIMELINE_FILTER_STORAGE_KEY = 'helmdesk.conversation.timeline_filter';

function isTimelineFilter(value: unknown): value is TimelineFilter {
  return value === 'all' || value === 'messages' || value === 'events';
}

function getStoredTimelineFilter(): TimelineFilter {
  if (typeof window === 'undefined') {
    return 'all';
  }

  const stored = window.localStorage.getItem(TIMELINE_FILTER_STORAGE_KEY);

  return isTimelineFilter(stored) ? stored : 'all';
}

const timelineFilter = ref<TimelineFilter>(getStoredTimelineFilter());

const chatScrollRef = ref<HTMLElement | null>(null);

/**
 * 在「加载更早记录」时保留用户当前可视位置：
 * 记录 prepend 前的 scrollHeight / scrollTop，prepend 之后按 delta 复位。
 */
let prependAnchor: { prevHeight: number; prevTop: number } | null = null;

let activeController: AbortController | null = null;

const scrollChatToBottom = async () => {
  await nextTick();
  const el = chatScrollRef.value;
  if (!el) {
    return;
  }
  el.scrollTop = el.scrollHeight;
  /*
   * Sheet 打开时伴随动画，首帧 scrollHeight 可能尚未稳定；
   * 再在下一个 animation frame 上复位一次，确保最终停在底部。
   */
  requestAnimationFrame(() => {
    const current = chatScrollRef.value;
    if (current) {
      current.scrollTop = current.scrollHeight;
    }
  });
};

const fetchDetail = async (append = false) => {
  const cursor = append ? detail.value?.timeline.next_cursor : null;
  const requestedConversationId = props.conversationId;

  if (activeController) {
    activeController.abort();
  }

  const controller = new AbortController();
  activeController = controller;

  if (append) {
    loadingMore.value = true;
    const el = chatScrollRef.value;
    if (el) {
      prependAnchor = {
        prevHeight: el.scrollHeight,
        prevTop: el.scrollTop,
      };
    }
  } else {
    loading.value = true;
  }

  try {
    const response = await fetch(
      app.conversations.show.url(
        {
          id: requestedConversationId,
        },
        {
          query: {
            cursor: cursor || undefined,
          },
        },
      ),
      {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        signal: controller.signal,
      },
    );

    if (!response.ok) {
      return;
    }

    const payload = (await response.json()) as ConversationDetailData;

    if (
      controller.signal.aborted ||
      requestedConversationId !== props.conversationId
    ) {
      return;
    }

    if (!append || !detail.value) {
      detail.value = payload;
    } else {
      // 更早的记录放在当前时间线前面，整体保持时间升序。
      detail.value = {
        ...payload,
        timeline: {
          ...payload.timeline,
          items: [...payload.timeline.items, ...detail.value.timeline.items],
        },
      };
    }
  } catch (error) {
    if ((error as DOMException)?.name === 'AbortError') {
      return;
    }
    throw error;
  } finally {
    if (activeController === controller) {
      activeController = null;
    }
    if (!controller.signal.aborted) {
      loading.value = false;
      loadingMore.value = false;

      /*
       * 必须在 loading/loadingMore 置回 false 之后再处理滚动：
       * 此时 v-if="loading" 才会切换到真正的消息列表，scrollHeight 才是正确值。
       */
      if (append) {
        await nextTick();
        const el = chatScrollRef.value;
        if (el && prependAnchor) {
          const delta = el.scrollHeight - prependAnchor.prevHeight;
          el.scrollTop = prependAnchor.prevTop + delta;
        }
        prependAnchor = null;
      } else {
        await scrollChatToBottom();
      }
    }
  }
};

watch(
  () => props.conversationId,
  () => {
    detail.value = null;
    fetchDetail(false);
  },
  { immediate: true },
);

/** 保存聊天记录筛选偏好，并自动回到最新一条。 */
watch(timelineFilter, () => {
  if (typeof window !== 'undefined') {
    window.localStorage.setItem(
      TIMELINE_FILTER_STORAGE_KEY,
      timelineFilter.value,
    );
  }

  if (detail.value) {
    scrollChatToBottom();
  }
});

onBeforeUnmount(() => {
  if (activeController) {
    activeController.abort();
    activeController = null;
  }
});

const title = computed(
  () =>
    detail.value?.conversation.subject ||
    props.fallbackConversation?.subject ||
    t('无主题会话'),
);

const secondaryStatusLabel = computed(() => {
  const conversation = detail.value?.conversation;
  if (!conversation) {
    return null;
  }

  if (conversation.status === 'closed') {
    return null;
  }

  if (conversation.waiting_for_visitor_reply_label) {
    return conversation.waiting_for_visitor_reply_label;
  }

  return conversation.inbox_status_label;
});

const allItems = computed<TimelineEntryData[]>(
  () => detail.value?.timeline.items ?? [],
);

const filteredItems = computed<TimelineEntryData[]>(() => {
  if (timelineFilter.value === 'messages') {
    return allItems.value.filter((item) => item.type === 'message');
  }
  if (timelineFilter.value === 'events') {
    return allItems.value.filter((item) => item.type === 'event');
  }
  return allItems.value;
});

function timelineItemSpacingClass(
  item: TimelineEntryData,
  index: number,
): string {
  if (index === 0) {
    return '';
  }

  const previousItem = filteredItems.value[index - 1];

  if (item.type === 'event' && previousItem?.type === 'event') {
    return 'mt-0.5';
  }

  return 'mt-3';
}

const filterCounts = computed(() => ({
  all: allItems.value.length,
  messages: allItems.value.filter((item) => item.type === 'message').length,
  events: allItems.value.filter((item) => item.type === 'event').length,
}));

const contactInitial = computed<string>(() =>
  getAvatarInitial(detail.value?.contact_summary?.name),
);

const openContactDetail = () => {
  const contactId = detail.value?.contact_summary?.id;
  if (!contactId) {
    return;
  }
  emit('viewContact', contactId);
};

/** 接近顶部时自动加载更早的历史，让用户连续向上翻阅。 */
const AUTO_LOAD_THRESHOLD_PX = 80;

const handleChatScroll = (event: Event) => {
  const el = event.currentTarget as HTMLElement | null;
  if (!el) {
    return;
  }
  if (loading.value || loadingMore.value) {
    return;
  }
  if (!detail.value?.timeline.next_cursor) {
    return;
  }
  if (el.scrollTop <= AUTO_LOAD_THRESHOLD_PX) {
    fetchDetail(true);
  }
};
</script>

<template>
  <div class="flex h-full flex-col">
    <div class="space-y-3 border-b px-6 py-4">
      <div class="space-y-2">
        <div class="text-lg leading-tight font-semibold">{{ title }}</div>
        <div v-if="detail" class="flex flex-wrap gap-2">
          <Badge variant="secondary">
            {{ detail.conversation.status_label }}
          </Badge>
          <Badge v-if="secondaryStatusLabel" variant="outline">
            {{ secondaryStatusLabel }}
          </Badge>
        </div>
      </div>

      <div
        v-if="detail"
        class="grid grid-cols-2 gap-x-4 gap-y-2 md:grid-cols-4"
      >
        <button
          type="button"
          class="group -mx-1.5 flex items-center gap-2 rounded-md px-1.5 py-1 text-left transition hover:bg-muted disabled:cursor-default disabled:hover:bg-transparent"
          :disabled="!detail.contact_summary?.id"
          @click="openContactDetail"
        >
          <Avatar class="size-7 shrink-0">
            <AvatarImage
              v-if="detail.contact_summary?.avatar_url"
              :src="detail.contact_summary.avatar_url"
            />
            <AvatarFallback class="bg-muted text-[11px] text-muted-foreground">
              {{ contactInitial }}
            </AvatarFallback>
          </Avatar>
          <div class="min-w-0 flex-1">
            <div class="text-[11px] text-muted-foreground">
              {{ t('联系人') }}
            </div>
            <div class="flex items-center gap-0.5 text-sm font-medium">
              <span class="truncate">
                {{
                  formatVisitorName(
                    detail.contact_summary?.name,
                    detail.contact_summary?.id,
                  )
                }}
              </span>
              <ArrowUpRight
                v-if="detail.contact_summary?.id"
                class="size-3 shrink-0 text-muted-foreground opacity-0 transition group-hover:opacity-100"
              />
            </div>
          </div>
        </button>

        <div>
          <div class="text-[11px] text-muted-foreground">
            {{ t('接待方案版本') }}
          </div>
          <div class="truncate text-sm font-medium">
            <template v-if="detail.reception_plan_version_summary">
              {{ detail.reception_plan_version_summary.plan_name }} ·
              {{ t('版本') }} v{{
                detail.reception_plan_version_summary.version_number
              }}
            </template>
            <template v-else>-</template>
          </div>
        </div>

        <div>
          <div class="text-[11px] text-muted-foreground">{{ t('分配给') }}</div>
          <div class="truncate text-sm font-medium">
            {{ detail.assigned_teammate?.name || '-' }}
          </div>
        </div>

        <div>
          <div class="text-[11px] text-muted-foreground">
            {{ t('最后消息时间') }}
          </div>
          <div class="truncate text-sm font-medium">
            {{
              detail.conversation.last_message_at
                ? formatDateTime(detail.conversation.last_message_at)
                : '-'
            }}
          </div>
        </div>
      </div>
    </div>

    <div class="flex items-center justify-between gap-2 border-b px-6 py-2.5">
      <div class="text-sm font-medium">{{ t('聊天记录') }}</div>
      <div class="inline-flex rounded-md border bg-muted/40 p-0.5 text-xs">
        <button
          v-for="option in [
            { key: 'all', label: t('全部'), count: filterCounts.all },
            {
              key: 'messages',
              label: t('仅对话'),
              count: filterCounts.messages,
            },
            {
              key: 'events',
              label: t('只看处理记录'),
              count: filterCounts.events,
            },
          ]"
          :key="option.key"
          type="button"
          :class="[
            'rounded px-2.5 py-1 transition',
            timelineFilter === option.key
              ? 'bg-background font-medium text-foreground shadow-sm'
              : 'text-muted-foreground hover:text-foreground',
          ]"
          @click="timelineFilter = option.key as TimelineFilter"
        >
          {{ option.label }}
          <span class="ml-1 text-muted-foreground/70">{{ option.count }}</span>
        </button>
      </div>
    </div>

    <div
      ref="chatScrollRef"
      class="flex-1 overflow-y-auto px-6 py-4"
      @scroll="handleChatScroll"
    >
      <div v-if="loading" class="text-sm text-muted-foreground">
        {{ t('加载中...') }}
      </div>

      <template v-else-if="detail">
        <!-- 顶部提示区：自动加载时显示进度，已到头时显示提示 -->
        <div
          v-if="detail.timeline.next_cursor || loadingMore"
          class="mb-3 flex justify-center py-1 text-xs text-muted-foreground/70"
        >
          <span v-if="loadingMore">{{ t('正在加载更早的记录…') }}</span>
          <span v-else>{{ t('向上滚动查看更早的记录') }}</span>
        </div>
        <div
          v-else-if="filteredItems.length > 0"
          class="mb-3 flex justify-center py-1 text-xs text-muted-foreground/70"
        >
          {{ t('没有更早的记录了') }}
        </div>

        <div
          v-for="(item, index) in filteredItems"
          :key="item.id"
          :class="timelineItemSpacingClass(item, index)"
        >
          <ConversationMessageBubble
            v-if="item.type === 'message'"
            :entry="item"
            :contact-summary="detail.contact_summary"
            :translation-enabled="false"
          />
          <ConversationEventLine v-else :entry="item" />
        </div>

        <div
          v-if="filteredItems.length === 0"
          class="py-8 text-center text-sm text-muted-foreground"
        >
          {{
            timelineFilter === 'messages'
              ? t('暂无聊天消息')
              : timelineFilter === 'events'
                ? t('暂无处理记录')
                : t('暂无记录')
          }}
        </div>
      </template>
    </div>
  </div>
</template>
