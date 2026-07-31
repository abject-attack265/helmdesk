<!--
  会话消息气泡，展示原文、译文、附件及消息操作。
-->
<script setup lang="ts">
import ImagePreviewDialog from '@/components/common/ImagePreviewDialog.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuSeparator,
  ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import { hasTranslatableLetters } from '@/lib/translationText';
import { formatFileSize } from '@/lib/format';
import { getAvatarInitial } from '@/lib/initials';
import { renderMarkdownToSafeHtml } from '@/lib/markdown';
import type {
  ConversationContactSummaryData,
  TimelineEntryData,
} from '@/types/generated';
import { LoaderCircle, Paperclip } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = withDefaults(
  defineProps<{
    entry: TimelineEntryData;
    contactSummary?: ConversationContactSummaryData | null;
    canRecall?: boolean;
    canQuote?: boolean;
    canRetry?: boolean;
    isTranslating?: boolean;
    translationLocale?: string | null;
    translationEnabled: boolean;
    /** 是否配置了可用翻译供应商（决定是否显示「翻译/重新翻译」按钮） */
    translationAvailable?: boolean;
  }>(),
  {
    canRecall: false,
    canQuote: false,
    canRetry: false,
    isTranslating: false,
    translationAvailable: false,
  },
);

const emit = defineEmits<{
  (event: 'recall', messageId: string): void;
  (event: 'reedit', content: string): void;
  (event: 'quote', entry: TimelineEntryData): void;
  (event: 'translate', payload: { messageId: string; force: boolean }): void;
  (event: 'retry', messageId: string): void;
}>();

const { t, locale } = useI18n();
const { formatDateTime } = useDateTime();
const { formatVisitorName } = useVisitorDisplay();

const role = computed(() => props.entry.role);
const kind = computed(() => props.entry.kind);

const isFromVisitor = computed(() => role.value === 'visitor');
const isSystemLike = computed(() => kind.value === 'summary');
const isAi = computed(() => role.value === 'ai');

const copyDebugId = async (value: string): Promise<void> => {
  try {
    await navigator.clipboard.writeText(value);
  } catch (error) {
    console.warn('[conversation-message] 复制调试标识失败', {
      value,
      error,
    });
  }
};

const alignRight = computed(() => !isFromVisitor.value && !isSystemLike.value);

const isRecalled = computed(() => Boolean(props.entry.recalled_at));
const isSending = computed(() => props.entry.delivery_status === 'sending');
const isDeliveryFailed = computed(
  () => props.entry.delivery_status === 'failed',
);

// 访客在渠道侧（如 Telegram）编辑过消息时，payload.edited_at 记录最近编辑时间。
const editedAt = computed(() => {
  const value = props.entry.payload?.edited_at;
  return typeof value === 'string' && value !== '' ? value : null;
});

const hasTextContent = computed(() => !!props.entry.content);
const hasAttachments = computed(() => attachments.value.length > 0);
const isAttachmentOnly = computed(
  () => hasAttachments.value && !hasTextContent.value && !isSystemLike.value,
);

// 撤回 2 分钟时效窗口。不维护实时时钟：菜单打开时取一次 Date.now() 快照即可——
// 菜单关闭期间按钮不可见，没有"瞬时变灰"的需求；菜单关到再开会重新取快照。
const RECALL_WINDOW_MS = 2 * 60 * 1000;
const menuOpenedAt = ref<number>(0);

function messageCreatedAtMs(): number | null {
  if (props.entry.type !== 'message') {
    return null;
  }
  const ts = Date.parse(props.entry.occurred_at);

  return Number.isNaN(ts) ? null : ts;
}

function isWithinRecallWindow(referenceNow: number): boolean {
  const created = messageCreatedAtMs();
  if (created === null) {
    return false;
  }

  return referenceNow - created <= RECALL_WINDOW_MS;
}

const canCopy = computed(
  () =>
    !isRecalled.value &&
    typeof props.entry.content === 'string' &&
    props.entry.content.length > 0,
);

const showRecallAction = computed(() => Boolean(props.canRecall));
const showQuoteAction = computed(() => Boolean(props.canQuote));

// 仅在菜单已展开（menuOpenedAt > 0）时才用快照时间做判断；菜单未开时 disabled 取值无意义。
const recallDisabled = computed(
  () => isRecalled.value || !isWithinRecallWindow(menuOpenedAt.value),
);

function handleMenuOpenChange(open: boolean): void {
  if (open) {
    menuOpenedAt.value = Date.now();
  }
}

// 工具消息 / 系统摘要不暴露右键菜单；其他业务消息只要不是空容器都允许右键。
const showContextMenu = computed(() => {
  if (props.entry.type !== 'message') {
    return false;
  }
  if (kind.value === 'tool_call' || kind.value === 'tool_result') {
    return false;
  }

  return true;
});

const recalledContent = computed(() => props.entry.recalled_content ?? null);

type MessageTranslationPayload = {
  translations?: Record<string, { text: string }>;
};

const translationVisible = ref(true);
const translations = computed(() => {
  const payload = props.entry.payload as MessageTranslationPayload | null;

  return payload?.translations ?? {};
});
const displayTranslationLocale = computed(
  () => props.translationLocale || locale.value,
);
const translatedText = computed(() => {
  return translations.value[displayTranslationLocale.value]?.text ?? null;
});
const hasTranslation = computed(
  () => translatedText.value !== null && !isRecalled.value,
);
const translationActive = computed(
  () => props.translationEnabled && hasTranslation.value,
);
// 所有角色的正文统一按 markdown 渲染（表格/列表/加粗/代码块等），与 C 端访客气泡共用同一套渲染与样式。
const sourceContentHtml = computed(() =>
  typeof props.entry.content === 'string'
    ? renderMarkdownToSafeHtml(props.entry.content)
    : '',
);
const translatedContentHtml = computed(() =>
  typeof translatedText.value === 'string'
    ? renderMarkdownToSafeHtml(translatedText.value)
    : '',
);
const hasTranslationToggle = computed(
  () =>
    translationActive.value &&
    !props.isTranslating &&
    typeof props.entry.content === 'string' &&
    props.entry.content !== translatedText.value,
);
const showTranslatedContent = computed(
  () => hasTranslationToggle.value && translationVisible.value,
);
const translationActionButtonClass = computed(() =>
  role.value === 'teammate'
    ? 'text-[10px] text-primary-foreground/70 hover:text-primary-foreground disabled:opacity-50'
    : 'text-[10px] text-muted-foreground/70 hover:text-foreground disabled:opacity-50',
);

// 仅为含语言文字的业务消息提供翻译操作。
const canTranslateMessage = computed(() => {
  if (!props.translationEnabled || !props.translationAvailable) {
    return false;
  }
  if (props.entry.type !== 'message' || isRecalled.value) {
    return false;
  }
  if (kind.value !== 'text' || typeof props.entry.content !== 'string') {
    return false;
  }
  if (!hasTranslatableLetters(props.entry.content)) {
    return false;
  }
  if (!['visitor', 'ai', 'teammate'].includes(role.value ?? '')) {
    return false;
  }
  return true;
});

// 已有译文时强制覆盖，等待新载荷期间只展示翻译中状态。
function requestTranslate(): void {
  translationVisible.value = true;
  emit('translate', {
    messageId: props.entry.id,
    force: hasTranslation.value,
  });
}

function requestRecall(): void {
  if (!props.canRecall || isRecalled.value) {
    return;
  }
  // 点击瞬间重新核对 2 分钟撤回窗口。
  if (!isWithinRecallWindow(Date.now())) {
    return;
  }
  emit('recall', props.entry.id);
}

function requestQuote(): void {
  if (props.canQuote && props.entry.type === 'message' && !isRecalled.value) {
    emit('quote', props.entry);
  }
}

async function copyContent(): Promise<void> {
  const text = showTranslatedContent.value
    ? translatedText.value
    : props.entry.content;
  if (typeof text !== 'string' || text.length === 0) {
    return;
  }
  try {
    await navigator.clipboard.writeText(text);
  } catch (error) {
    console.warn('[conversation-message] 复制消息内容失败', {
      messageId: props.entry.id,
      error,
    });
  }
}

function requestReedit(): void {
  const content = recalledContent.value;
  if (typeof content === 'string' && content.length > 0) {
    emit('reedit', content);
  }
}

function requestRetry(): void {
  emit('retry', props.entry.id);
}

const senderName = computed<string>(() => {
  if (role.value === 'visitor') {
    return formatVisitorName(
      props.contactSummary?.name,
      props.contactSummary?.id,
    );
  }

  if (role.value === 'ai') {
    return props.entry.sender_name || t('AI 助手');
  }

  if (role.value === 'teammate') {
    return props.entry.sender_name || t('客服');
  }

  if (role.value === 'tool') {
    return t('工具');
  }

  return props.entry.subtype_label;
});

const avatarUrl = computed<string | null>(() => {
  if (role.value === 'visitor') {
    return props.contactSummary?.avatar_url ?? null;
  }
  if (role.value === 'teammate') {
    return props.entry.sender_avatar_url || null;
  }
  return null;
});

const initial = computed<string>(() => getAvatarInitial(senderName.value));

// 头像通过灰阶明度区分访客、AI、客服和工具消息。
const avatarFallbackClass = computed<string>(() => {
  if (isSystemLike.value) {
    return 'bg-muted text-muted-foreground';
  }
  switch (role.value) {
    case 'teammate':
      return 'bg-primary text-primary-foreground';
    case 'ai':
      return 'bg-muted text-muted-foreground';
    case 'tool':
      return 'border bg-background text-muted-foreground';
    case 'visitor':
    default:
      return 'border bg-background text-foreground';
  }
});

// flex 列下的气泡需要 min-w-0 + max-w-full 才能被父级 max-w 夹回，
// 否则气泡会被自身长文本撑到 max-content，从而冲出列外被裁切。
const BUBBLE_SIZING_GUARD = 'min-w-0 max-w-full';

// 引用消息的侧边线根据消息方向贴近气泡外侧。
const quoteBorderClass = computed(() =>
  alignRight.value
    ? 'border-r-2 border-muted-foreground/30 pr-2'
    : 'border-l-2 border-muted-foreground/30 pl-2',
);

const bubbleClass = computed<string>(() => {
  if (isSystemLike.value) {
    return 'w-full rounded-md border-l-2 border-foreground/40 bg-muted/40 px-3 py-2 text-sm';
  }

  if (isAttachmentOnly.value) {
    return '';
  }

  if (alignRight.value) {
    if (role.value === 'ai') {
      return `${BUBBLE_SIZING_GUARD} rounded-2xl rounded-br-sm bg-muted px-3 py-2 text-sm text-foreground`;
    }
    if (role.value === 'tool') {
      return `${BUBBLE_SIZING_GUARD} rounded-2xl rounded-br-sm border bg-background px-3 py-2 text-sm text-muted-foreground`;
    }
    return `${BUBBLE_SIZING_GUARD} rounded-2xl rounded-br-sm bg-primary px-3 py-2 text-sm text-primary-foreground`;
  }

  return `${BUBBLE_SIZING_GUARD} rounded-2xl rounded-bl-sm border bg-background px-3 py-2 text-sm text-foreground shadow-xs`;
});

const translationBubbleClass = computed(() => {
  const cornerClass = alignRight.value ? 'rounded-tr-sm' : 'rounded-tl-sm';

  return `${BUBBLE_SIZING_GUARD} rounded-2xl ${cornerClass} bg-muted/50 px-3 py-2 text-sm text-foreground ring-1 ring-inset ring-foreground/10`;
});

const kindBadgeLabel = computed<string | null>(() => {
  if (!kind.value || kind.value === 'text') {
    return null;
  }
  // subtype_label 使用“角色 · 类型”格式，气泡只展示类型。
  const parts = props.entry.subtype_label.split('·');
  return parts.length > 1
    ? parts[parts.length - 1].trim()
    : props.entry.subtype_label;
});

const toolPayloadText = computed<string | null>(() => {
  if (kind.value !== 'tool_call' && kind.value !== 'tool_result') {
    return null;
  }
  if (!props.entry.payload) {
    return null;
  }
  return JSON.stringify(props.entry.payload, null, 2);
});

interface BubbleAttachment {
  id: string;
  name: string;
  mime_type: string;
  byte_size: number;
  url: string;
}

const attachments = computed<BubbleAttachment[]>(() => {
  const raw = props.entry.payload?.attachments;
  return Array.isArray(raw) ? (raw as BubbleAttachment[]) : [];
});

const imagePreviewItems = computed(() =>
  attachments.value.filter((attachment) =>
    attachment.mime_type.startsWith('image/'),
  ),
);

const previewOpen = ref(false);
const activePreviewId = ref<string | null>(null);
const quotedPreviewOpen = ref(false);

function openImagePreview(attachment: BubbleAttachment): void {
  activePreviewId.value = attachment.id;
  previewOpen.value = true;
}

function normalizeAttachmentList(value: unknown): BubbleAttachment[] {
  if (Array.isArray(value)) {
    return value as BubbleAttachment[];
  }
  if (value && typeof value === 'object') {
    return Object.values(value) as BubbleAttachment[];
  }

  return [];
}

const quotedAttachments = computed<BubbleAttachment[]>(() =>
  normalizeAttachmentList(props.entry.quoted_message?.attachments),
);

const quotedImage = computed<BubbleAttachment | null>(
  () =>
    quotedAttachments.value.find((attachment) =>
      attachment.mime_type.startsWith('image/'),
    ) ?? null,
);

const quotedFile = computed<BubbleAttachment | null>(
  () =>
    quotedAttachments.value.find(
      (attachment) => !attachment.mime_type.startsWith('image/'),
    ) ?? null,
);

const quotedPreviewImages = computed(() =>
  quotedImage.value ? [quotedImage.value] : [],
);

const quotedSenderName = computed(() => {
  const quoted = props.entry.quoted_message;
  if (!quoted) {
    return '';
  }
  if (quoted.role === 'visitor') {
    return formatVisitorName(
      props.contactSummary?.name,
      props.contactSummary?.id,
    );
  }
  return quoted.sender_name;
});

const quotedFullContent = computed(() => {
  const content = props.entry.quoted_message?.content?.trim();
  if (content) {
    return content;
  }

  return props.entry.quoted_message?.preview || t('无内容');
});

const quotedPreviewLabel = computed(
  () =>
    `${quotedSenderName.value}：${props.entry.quoted_message?.preview ?? ''}`,
);

const quotedPreviewWidthStyle = computed(() =>
  buildQuotedPreviewWidthStyle(quotedPreviewLabel.value),
);

function displayWidth(text: string): number {
  let width = 0;

  for (const char of text) {
    width += /[^\u0000-\u00ff]/u.test(char) ? 2 : 1;
  }

  return width;
}

// 引用消息每行最大宽度（单位 ch；CJK 字符按 2 个宽度单位计）。
const QUOTED_MAX_WIDTH_CH = 32;
const QUOTED_MIN_BALANCED_WIDTH_CH = 18;
const QUOTED_FULL_WIDTH_THRESHOLD_CH = QUOTED_MAX_WIDTH_CH * 2.4;
const QUOTED_BALANCE_RATIO = 2.18;

function buildQuotedPreviewWidthStyle(text: string): Record<string, string> {
  const width = displayWidth(text);

  // 一行能放下：按内容自适应，不强制拉宽。
  if (width <= QUOTED_MAX_WIDTH_CH) {
    return { width: 'fit-content' };
  }

  // 明显超过两行可承载量：顶满最大宽度，靠 line-clamp-2 在尾部加省略号。
  if (width >= QUOTED_FULL_WIDTH_THRESHOLD_CH) {
    return { width: `${QUOTED_MAX_WIDTH_CH}ch` };
  }

  // 中等长度：略窄于均分宽度，让浏览器更倾向于拆出长度接近的两行。
  return {
    width: `${Math.min(
      QUOTED_MAX_WIDTH_CH,
      Math.max(
        QUOTED_MIN_BALANCED_WIDTH_CH,
        Math.ceil(width / QUOTED_BALANCE_RATIO),
      ),
    )}ch`,
  };
}

function openQuotedImage(): void {
  if (!quotedImage.value) {
    return;
  }
  activePreviewId.value = quotedImage.value.id;
  quotedPreviewOpen.value = true;
}

function openQuotedFile(): void {
  if (!quotedFile.value) {
    return;
  }
  window.open(quotedFile.value.url, '_blank', 'noopener,noreferrer');
}
</script>

<template>
  <div
    :class="[
      'flex items-start gap-2',
      isSystemLike
        ? 'justify-center'
        : alignRight
          ? 'justify-end'
          : 'justify-start',
    ]"
  >
    <Avatar
      v-if="isRecalled && !isSystemLike && !alignRight"
      class="mt-5 size-8"
    >
      <AvatarImage v-if="avatarUrl" :src="avatarUrl" />
      <AvatarFallback :class="avatarFallbackClass">
        {{ initial }}
      </AvatarFallback>
    </Avatar>

    <div
      :class="[
        'flex max-w-[75%] flex-col gap-1',
        isSystemLike
          ? 'w-full items-stretch'
          : alignRight
            ? ['items-end', !isRecalled && 'mr-10']
            : ['items-start', !isRecalled && 'ml-10'],
      ]"
    >
      <div
        v-if="!isSystemLike"
        class="flex items-baseline gap-2 text-xs text-muted-foreground"
      >
        <Badge
          v-if="kindBadgeLabel && !isAttachmentOnly"
          variant="outline"
          class="h-5 px-1.5 text-[10px]"
        >
          {{ kindBadgeLabel }}
        </Badge>
        <span
          class="text-[11px] text-muted-foreground/80"
          :title="formatDateTime(entry.occurred_at)"
        >
          {{ formatDateTime(entry.occurred_at, 'MM-DD HH:mm') }}
        </span>
        <span
          v-if="editedAt"
          class="text-[11px] text-muted-foreground/60"
          :title="formatDateTime(editedAt)"
        >
          {{ t('已编辑') }}
        </span>
      </div>

      <div
        v-if="isRecalled"
        class="flex flex-wrap items-baseline gap-2 rounded-md border border-dashed border-muted-foreground/30 bg-muted/40 px-3 py-2 text-xs text-muted-foreground italic"
      >
        <span>{{
          recalledContent !== null
            ? t('你撤回了一条消息')
            : t('对方撤回了一条消息')
        }}</span>
        <button
          v-if="recalledContent !== null"
          type="button"
          class="text-primary not-italic hover:underline"
          @click="requestReedit"
        >
          {{ t('重新编辑') }}
        </button>
      </div>
      <div v-if="!isRecalled" class="relative max-w-full">
        <Avatar
          v-if="!isSystemLike"
          :class="[
            'absolute bottom-0 size-8',
            alignRight ? 'left-full ml-2' : 'right-full mr-2',
          ]"
        >
          <AvatarImage v-if="avatarUrl" :src="avatarUrl" />
          <AvatarFallback :class="avatarFallbackClass">
            {{ initial }}
          </AvatarFallback>
        </Avatar>

        <ContextMenu v-if="showContextMenu" @update:open="handleMenuOpenChange">
          <ContextMenuTrigger as-child>
            <div :class="bubbleClass">
              <div
                v-if="isSystemLike"
                class="mb-1 flex items-baseline gap-2 text-xs"
              >
                <span class="font-medium">{{ senderName }}</span>
                <Badge
                  v-if="kindBadgeLabel && !isAttachmentOnly"
                  variant="outline"
                  class="h-5 px-1.5 text-[10px]"
                >
                  {{ kindBadgeLabel }}
                </Badge>
                <span
                  class="text-[11px] text-muted-foreground/80"
                  :title="formatDateTime(entry.occurred_at)"
                >
                  {{ formatDateTime(entry.occurred_at, 'MM-DD HH:mm') }}
                </span>
              </div>

              <div
                v-if="entry.content"
                class="ai-markdown [overflow-wrap:anywhere] break-words"
                v-html="sourceContentHtml"
              />
              <div v-if="attachments.length" class="mt-2 space-y-2">
                <template
                  v-for="attachment in attachments"
                  :key="attachment.id"
                >
                  <button
                    v-if="attachment.mime_type.startsWith('image/')"
                    type="button"
                    class="inline-block cursor-zoom-in rounded-xl align-top focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                    :aria-label="
                      t('查看图片：{name}', { name: attachment.name })
                    "
                    @click="openImagePreview(attachment)"
                  >
                    <img
                      :src="attachment.url"
                      :alt="attachment.name"
                      data-message-attachment-image="true"
                      class="max-h-64 max-w-64 animate-[msg-img-in_150ms_ease-out] rounded-xl"
                    />
                  </button>
                  <a
                    v-else
                    :href="attachment.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="block overflow-hidden rounded-xl border bg-background/60"
                  >
                    <div class="px-3 py-2 text-xs">
                      <div class="font-medium">{{ attachment.name }}</div>
                      <div class="mt-0.5 opacity-70">
                        {{ formatFileSize(attachment.byte_size) }}
                      </div>
                    </div>
                  </a>
                </template>
              </div>

              <div
                v-if="
                  !entry.content && !attachments.length && !entry.quoted_message
                "
                class="italic opacity-70"
              >
                {{ t('无内容') }}
              </div>

              <div
                v-if="
                  hasTranslationToggle ||
                  canTranslateMessage ||
                  props.isTranslating
                "
                class="mt-1 flex items-center gap-2"
              >
                <button
                  v-if="hasTranslationToggle"
                  type="button"
                  :class="translationActionButtonClass"
                  @click.stop="translationVisible = !translationVisible"
                >
                  {{ translationVisible ? t('隐藏译文') : t('显示译文') }}
                </button>
                <button
                  v-if="canTranslateMessage"
                  type="button"
                  :class="translationActionButtonClass"
                  :disabled="props.isTranslating"
                  @click.stop="requestTranslate"
                >
                  {{ hasTranslation ? t('重新翻译') : t('翻译') }}
                </button>
              </div>
            </div>
          </ContextMenuTrigger>
          <ContextMenuContent class="w-44">
            <ContextMenuItem :disabled="!canCopy" @select="copyContent">
              {{ t('复制') }}
            </ContextMenuItem>
            <ContextMenuItem
              v-if="hasTranslationToggle"
              @select="translationVisible = !translationVisible"
            >
              {{ translationVisible ? t('隐藏译文') : t('显示译文') }}
            </ContextMenuItem>
            <ContextMenuItem v-if="showQuoteAction" @select="requestQuote">
              {{ t('引用') }}
            </ContextMenuItem>

            <!-- 调试标识用于在 AI 调用日志中定位接待与翻译调用。 -->
            <ContextMenuSeparator />
            <ContextMenuItem
              v-if="isAi && entry.turn_id"
              @select="copyDebugId(entry.turn_id)"
            >
              {{ t('复制本次接待编号') }}
            </ContextMenuItem>
            <ContextMenuItem @select="copyDebugId(entry.id)">
              {{ t('复制消息编号') }}
            </ContextMenuItem>

            <ContextMenuSeparator v-if="showRecallAction" />
            <ContextMenuItem
              v-if="showRecallAction"
              variant="destructive"
              :disabled="recallDisabled"
              @select="requestRecall"
            >
              {{ t('撤回') }}
            </ContextMenuItem>
          </ContextMenuContent>
        </ContextMenu>
        <div v-if="!showContextMenu" :class="bubbleClass">
          <div
            v-if="isSystemLike"
            class="mb-1 flex items-baseline gap-2 text-xs"
          >
            <span class="font-medium">{{ senderName }}</span>
            <Badge
              v-if="kindBadgeLabel && !isAttachmentOnly"
              variant="outline"
              class="h-5 px-1.5 text-[10px]"
            >
              {{ kindBadgeLabel }}
            </Badge>
            <span
              class="text-[11px] text-muted-foreground/80"
              :title="formatDateTime(entry.occurred_at)"
            >
              {{ formatDateTime(entry.occurred_at, 'MM-DD HH:mm') }}
            </span>
          </div>

          <div
            v-if="entry.content"
            class="ai-markdown [overflow-wrap:anywhere] break-words"
            v-html="sourceContentHtml"
          />
          <pre
            v-if="toolPayloadText"
            class="overflow-x-auto rounded bg-foreground/10 p-2 font-mono text-xs"
            >{{ toolPayloadText }}</pre>

          <div
            v-if="
              !entry.content &&
              !attachments.length &&
              !toolPayloadText &&
              !entry.quoted_message
            "
            class="italic opacity-70"
          >
            {{ t('无内容') }}
          </div>

          <div
            v-if="
              hasTranslationToggle || canTranslateMessage || props.isTranslating
            "
            class="mt-1 flex items-center gap-2"
          >
            <button
              v-if="hasTranslationToggle"
              type="button"
              :class="translationActionButtonClass"
              @click.stop="translationVisible = !translationVisible"
            >
              {{ translationVisible ? t('隐藏译文') : t('显示译文') }}
            </button>
            <button
              v-if="canTranslateMessage"
              type="button"
              :class="translationActionButtonClass"
              :disabled="props.isTranslating"
              @click.stop="requestTranslate"
            >
              {{ hasTranslation ? t('重新翻译') : t('翻译') }}
            </button>
          </div>
        </div>
      </div>
      <div
        v-if="!isRecalled && (props.isTranslating || showTranslatedContent)"
        :class="['-mt-0.5', translationBubbleClass]"
      >
        <div
          v-if="props.isTranslating"
          class="flex min-h-5 items-center"
          :title="t('翻译中')"
        >
          <LoaderCircle
            class="size-3.5 animate-spin opacity-70"
            aria-hidden="true"
          />
          <span class="sr-only">{{ t('翻译中') }}</span>
        </div>
        <div
          v-else
          class="ai-markdown [overflow-wrap:anywhere] break-words"
          v-html="translatedContentHtml"
        />
      </div>
      <div
        v-if="alignRight && !isRecalled && (isSending || isDeliveryFailed)"
        class="flex items-center gap-1.5 text-[11px] text-muted-foreground"
      >
        <LoaderCircle v-if="isSending" class="size-3 animate-spin" />
        <span>{{ isSending ? t('发送中') : t('发送失败') }}</span>
        <button
          v-if="isDeliveryFailed && props.canRetry"
          type="button"
          class="underline underline-offset-2 hover:text-foreground"
          @click="requestRetry"
        >
          {{ t('重试') }}
        </button>
      </div>
      <template v-if="entry.quoted_message && !isRecalled">
        <button
          v-if="quotedImage"
          type="button"
          :class="[
            'flex items-center gap-2 text-xs text-muted-foreground/70 transition-colors hover:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
            quoteBorderClass,
          ]"
          :aria-label="t('查看图片：{name}', { name: quotedImage.name })"
          @click.stop="openQuotedImage"
        >
          <span class="font-medium">{{ quotedSenderName }}：</span>
          <img
            :src="quotedImage.url"
            :alt="quotedImage.name"
            class="size-12 rounded-md object-cover"
          />
        </button>
        <button
          v-else-if="quotedFile"
          type="button"
          :class="[
            'flex items-center gap-2 text-xs text-muted-foreground/70 transition-colors hover:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
            quoteBorderClass,
          ]"
          @click.stop="openQuotedFile"
        >
          <span class="font-medium">{{ quotedSenderName }}：</span>
          <span
            class="inline-flex max-w-[20ch] items-center gap-1 rounded-md bg-muted/60 px-2 py-1"
          >
            <Paperclip class="size-3 shrink-0" />
            <span class="truncate">{{ quotedFile.name }}</span>
          </span>
        </button>
        <Popover v-else>
          <PopoverTrigger as-child>
            <button
              type="button"
              :class="[
                'text-xs text-muted-foreground/70 transition-colors hover:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
                quoteBorderClass,
              ]"
            >
              <span
                class="line-clamp-2 max-w-[32ch] text-left leading-5 [text-wrap:balance] [overflow-wrap:anywhere] break-all whitespace-normal"
                :style="quotedPreviewWidthStyle"
              >
                <span class="font-medium">{{ quotedSenderName }}：</span
                >{{ entry.quoted_message.preview }}
              </span>
            </button>
          </PopoverTrigger>
          <PopoverContent
            :side="alignRight ? 'left' : 'right'"
            align="center"
            :side-offset="20"
            :collision-padding="12"
            class="after:content-['']"
            :class="[
              'relative w-auto max-w-[min(20rem,75vw)] rounded-2xl border-0 bg-muted px-3 py-2 text-sm leading-6 shadow-md',
              'after:absolute after:top-1/2 after:h-0 after:w-0 after:-translate-y-1/2 after:border-y-8 after:border-y-transparent',
              alignRight
                ? 'after:left-full after:border-l-8 after:border-l-muted'
                : 'after:right-full after:border-r-8 after:border-r-muted',
            ]"
          >
            <div class="min-w-0 [overflow-wrap:anywhere] whitespace-pre-wrap">
              {{ quotedFullContent }}
            </div>
          </PopoverContent>
        </Popover>
      </template>
    </div>

    <Avatar
      v-if="isRecalled && !isSystemLike && alignRight"
      class="mt-5 size-8"
    >
      <AvatarImage v-if="avatarUrl" :src="avatarUrl" />
      <AvatarFallback :class="avatarFallbackClass">
        {{ initial }}
      </AvatarFallback>
    </Avatar>

    <ImagePreviewDialog
      v-if="imagePreviewItems.length"
      v-model:open="previewOpen"
      :images="imagePreviewItems"
      :initial-id="activePreviewId"
    />
    <ImagePreviewDialog
      v-if="quotedPreviewImages.length"
      v-model:open="quotedPreviewOpen"
      :images="quotedPreviewImages"
      :initial-id="activePreviewId"
    />
  </div>
</template>
