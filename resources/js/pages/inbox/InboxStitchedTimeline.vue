<!--
  收件箱时间线消费 ContactStitchedTimelineData 与会话摘要 Data，
  按会话边界展示当前会话可用的消息操作。
-->
<script setup lang="ts">
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import ConversationEventLine from '@/components/conversation/ConversationEventLine.vue';
import ConversationMessageBubble from '@/components/conversation/ConversationMessageBubble.vue';
import ConversationSummaryBlock from '@/pages/inbox/ConversationSummaryBlock.vue';
import type {
  ContactStitchedTimelineData,
  ContactTimelineEntryData,
  ConversationContactSummaryData,
  ConversationSummaryData,
  TagOptionData,
} from '@/types/generated';
import { computed } from 'vue';

const props = defineProps<{
  timeline: ContactStitchedTimelineData;
  contactSummary: ConversationContactSummaryData;
  currentConversationId: string;
  currentAssignedUserId: string | null;
  currentUserId: string;
  canReplyInCurrent: boolean;
  messageCommandsDisabled: boolean;
  translatingMessageIds: ReadonlySet<string>;
  translatingSummaryIds: ReadonlySet<string>;
  translationLocale: string;
  translationEnabled: boolean;
  translationAvailable: boolean;
  availableConversationTags: TagOptionData[];
  showEvents: boolean;
  highlightedMessageId: string | null;
}>();

const emit = defineEmits<{
  (event: 'recall', conversationId: string, messageId: string): void;
  (event: 'retry', conversationId: string, messageId: string): void;
  (event: 'reedit', content: string): void;
  (event: 'quote', entry: ContactTimelineEntryData): void;
  (
    event: 'translate-message',
    payload: { conversationId: string; messageId: string; force: boolean },
  ): void;
  (
    event: 'translate-summary',
    payload: { conversationId: string; force: boolean },
  ): void;
}>();

function canRecallEntry(entry: ContactTimelineEntryData): boolean {
  if (props.messageCommandsDisabled || !props.canReplyInCurrent) {
    return false;
  }

  if (entry.conversation_id !== props.currentConversationId) {
    return false;
  }

  if (entry.type !== 'message') {
    return false;
  }
  if (entry.role === null) {
    throw new Error(`收件箱消息 ${entry.id} 缺少角色`);
  }
  if (props.currentAssignedUserId !== props.currentUserId) {
    return false;
  }

  return (
    entry.role === 'ai' ||
    (entry.role === 'teammate' && entry.actor_user_id === props.currentUserId)
  );
}

function canRetryEntry(entry: ContactTimelineEntryData): boolean {
  return (
    !props.messageCommandsDisabled &&
    props.canReplyInCurrent &&
    entry.conversation_id === props.currentConversationId &&
    entry.type === 'message' &&
    entry.delivery_status === 'failed' &&
    (entry.role === 'ai' || entry.role === 'teammate')
  );
}

const { t } = useI18n();
const { formatDateTime } = useDateTime();

const conversationById = computed<Record<string, ConversationSummaryData>>(
  () => {
    const map: Record<string, ConversationSummaryData> = {};
    props.timeline.conversations.forEach((conversation) => {
      map[conversation.id] = conversation;
    });
    return map;
  },
);

type StreamItem =
  | {
      kind: 'boundary';
      key: string;
      conversation: ConversationSummaryData;
      index: number;
    }
  | { kind: 'entry'; key: string; entry: ContactTimelineEntryData }
  | { kind: 'hidden_events'; key: string };

const entriesByConversationId = computed<
  Record<string, ContactTimelineEntryData[]>
>(() => {
  const map: Record<string, ContactTimelineEntryData[]> = {};

  for (const entry of props.timeline.entries) {
    map[entry.conversation_id] ??= [];
    map[entry.conversation_id].push(entry);
  }

  return map;
});

const stream = computed<StreamItem[]>(() => {
  const items: StreamItem[] = [];
  const conversationIds = Object.keys(entriesByConversationId.value);

  for (const conversationId of conversationIds) {
    const conversation = conversationById.value[conversationId];
    if (!conversation) {
      throw new Error(`收件箱聊天记录缺少会话 ${conversationId} 的摘要`);
    }
    const conversationIndex =
      props.timeline.conversation_sequence_by_id[conversationId];
    if (conversationIndex === undefined) {
      throw new Error(`收件箱聊天记录缺少会话 ${conversationId} 的序号`);
    }

    const entries = entriesByConversationId.value[conversationId];
    const visibleEntries =
      props.showEvents === false
        ? entries.filter((entry) => entry.type === 'message')
        : entries;

    items.push({
      kind: 'boundary',
      key: `boundary:${conversationId}`,
      conversation,
      index: conversationIndex,
    });

    for (const entry of visibleEntries) {
      items.push({ kind: 'entry', key: `entry:${entry.id}`, entry });
    }

    if (
      props.showEvents === false &&
      entries.length > 0 &&
      visibleEntries.length === 0
    ) {
      items.push({
        kind: 'hidden_events',
        key: `hidden-events:${conversationId}`,
      });
    }
  }

  return items;
});

function isMessage(entry: ContactTimelineEntryData): boolean {
  return entry.type === 'message';
}

function boundaryStatusLabel(conversation: ConversationSummaryData): string {
  if (conversation.status === 'closed') {
    return t('已关闭');
  }
  return t('进行中');
}

function shouldShowBoundarySummary(
  conversation: ConversationSummaryData,
): boolean {
  return (
    conversation.id !== props.currentConversationId &&
    Boolean(conversation.summary) &&
    conversation.message_count >= 6
  );
}

function streamItemSpacingClass(item: StreamItem, index: number): string {
  if (index === 0) {
    return '';
  }

  const previousItem = stream.value[index - 1];

  if (item.kind === 'boundary') {
    return 'mt-6';
  }

  if (item.kind === 'hidden_events') {
    return 'mt-1';
  }

  if (
    item.kind === 'entry' &&
    previousItem?.kind === 'entry' &&
    item.entry.type === 'event' &&
    previousItem.entry.type === 'event'
  ) {
    return 'mt-0.5';
  }

  return 'mt-3';
}
</script>

<template>
  <div
    v-if="stream.length === 0"
    class="py-6 text-center text-sm text-muted-foreground"
  >
    {{ t('暂无消息') }}
  </div>
  <div v-else class="flex flex-col">
    <div
      v-for="(item, index) in stream"
      :key="item.key"
      :class="streamItemSpacingClass(item, index)"
      :data-inbox-timeline-message-id="
        item.kind === 'entry' && isMessage(item.entry) ? item.entry.id : null
      "
    >
      <div v-if="item.kind === 'boundary'" class="space-y-2">
        <div
          class="flex items-center gap-3 text-xs text-muted-foreground"
          :class="{
            'font-semibold text-foreground':
              props.currentConversationId === item.conversation.id,
          }"
        >
          <span class="h-px flex-1 bg-border"></span>
          <span
            class="rounded-full border bg-background px-3 py-1"
            :class="{
              'border-primary text-primary':
                props.currentConversationId === item.conversation.id,
            }"
          >
            {{ t('第 {n} 次会话', { n: item.index }) }} ·
            {{
              formatDateTime(item.conversation.created_at, 'YYYY-MM-DD HH:mm')
            }}
            · {{ boundaryStatusLabel(item.conversation) }}
          </span>
          <span class="h-px flex-1 bg-border"></span>
        </div>
        <ConversationSummaryBlock
          v-if="shouldShowBoundarySummary(item.conversation)"
          class="mx-auto max-w-3xl"
          :data-inbox-conversation-summary-id="item.conversation.id"
          :conversation="item.conversation"
          :translation-locale="props.translationLocale"
          :translation-enabled="props.translationEnabled"
          :available-tags="props.availableConversationTags"
          :is-translating="
            props.translatingSummaryIds.has(item.conversation.id)
          "
          :translation-available="props.translationAvailable"
          variant="boundary"
          @translate="
            (force) =>
              emit('translate-summary', {
                conversationId: item.conversation.id,
                force,
              })
          "
        />
      </div>

      <ConversationMessageBubble
        v-else-if="item.kind === 'entry' && isMessage(item.entry)"
        class="rounded-md transition-colors"
        :class="{
          'bg-foreground/5 ring-1 ring-foreground/20':
            props.highlightedMessageId === item.entry.id,
        }"
        :entry="item.entry"
        :contact-summary="props.contactSummary"
        :can-recall="canRecallEntry(item.entry)"
        :can-retry="canRetryEntry(item.entry)"
        :is-translating="props.translatingMessageIds.has(item.entry.id)"
        :translation-locale="props.translationLocale"
        :translation-enabled="props.translationEnabled"
        :translation-available="props.translationAvailable"
        :can-quote="
          props.canReplyInCurrent &&
          item.entry.conversation_id === props.currentConversationId
        "
        @recall="
          (messageId) => emit('recall', item.entry.conversation_id, messageId)
        "
        @retry="
          (messageId) => emit('retry', item.entry.conversation_id, messageId)
        "
        @reedit="(content) => emit('reedit', content)"
        @quote="() => emit('quote', item.entry)"
        @translate="
          (payload) =>
            emit('translate-message', {
              conversationId: item.entry.conversation_id,
              messageId: payload.messageId,
              force: payload.force,
            })
        "
      />
      <ConversationEventLine
        v-else-if="item.kind === 'entry'"
        :entry="item.entry"
      />
      <div v-else class="flex justify-center text-xs text-muted-foreground/70">
        {{ t('处理记录已隐藏') }}
      </div>
    </div>
  </div>
</template>
