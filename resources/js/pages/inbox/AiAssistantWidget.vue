<!--
  收件箱 AI 助手面板加载历史对话，并展示输入、附件和流式回复。
-->
<script setup lang="ts">
import SendAiAssistantMessageAction from '@/actions/App/Actions/AiChat/SendAiAssistantMessageAction';
import ShowAiAssistantMessagesAction from '@/actions/App/Actions/AiChat/ShowAiAssistantMessagesAction';
import StopAiAssistantMessageAction from '@/actions/App/Actions/AiChat/StopAiAssistantMessageAction';
import AiAssistantContextBrief from '@/pages/inbox/AiAssistantContextBrief.vue';
import TextMessageBubble from '@/pages/inbox/TextMessageBubble.vue';
import ToolMessageChip from '@/pages/inbox/ToolMessageChip.vue';
import { Button } from '@/components/ui/button';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import {
  resolveAttachmentUploadError,
  useAttachmentUploader,
} from '@/composables/useAttachmentUploader';
import { useI18n } from '@/composables/useI18n';
import { COMPOSER_EMOJIS } from '@/lib/composerEmojis';
import { aiChatTopic, openMercureEventSource } from '@/lib/mercure';
import type {
  AiAssistantMessageData,
  AiAssistantMessageSegmentData,
  AiAssistantThreadData,
  InboxContactProfileData,
} from '@/types/generated';
import axios from 'axios';
import { ImagePlus, Loader2, Send, Smile, Square, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
  conversationId: string;
  contactProfile: InboxContactProfileData;
  targetLocale: string;
  canTranslate: boolean;
  translationEnabled: boolean;
}>();

interface TextMessage {
  id: string;
  kind: 'text';
  role: 'user' | 'assistant';
  content: string;
  attachmentIds?: string[];
  imageUrls?: string[];
  pending?: boolean;
  error?: string;
}

interface ToolMessage {
  id: string;
  kind: 'tool_call' | 'tool_result';
  tool: string;
  display?: string;
  detail: string;
  expanded: boolean;
}

interface ThreadDivider {
  id: string;
  kind: 'thread_divider';
}

type ChatMessage = TextMessage | ToolMessage | ThreadDivider;

interface StreamPayload {
  type?: 'delta' | 'tool_call' | 'tool_result' | 'done' | 'error';
  content?: string | null;
  error?: string;
  tool?: string;
  tool_display?: string;
  args?: string;
}

const { t } = useI18n();

const inputValue = ref('');
const textareaRef = ref<HTMLTextAreaElement | null>(null);
const messagesRef = ref<HTMLDivElement | null>(null);
const messages = ref<ChatMessage[]>([]);
const isStreaming = ref(false);
const isStopping = ref(false);
let historyRequestToken = 0;
const currentRoundId = ref<string | null>(null);
const currentThreadId = ref<string | null>(null);
const previousThreadId = ref<string | null>(null);
const isPreparingNewThread = ref(false);

let currentAssistantId: string | null = null;

let currentEventSource: EventSource | null = null;

const hasMessages = computed(() =>
  messages.value.some((message) => message.kind === 'text'),
);

const findLastThreadDividerIndex = () => {
  for (let index = messages.value.length - 1; index >= 0; index -= 1) {
    if (messages.value[index]?.kind === 'thread_divider') {
      return index;
    }
  }

  return -1;
};

// AI 助手仅接受图片附件。
interface PendingImage {
  id: string;
  previewUrl: string;
  attachmentId: string | null;
  uploading: boolean;
  error: boolean;
}

const MAX_IMAGES = 6;
const { upload } = useAttachmentUploader();
const imageInputRef = ref<HTMLInputElement | null>(null);
const pendingImages = ref<PendingImage[]>([]);
const emojiPopoverOpen = ref(false);

const isUploadingImage = computed(() =>
  pendingImages.value.some((image) => image.uploading),
);
const hasReadyImage = computed(() =>
  pendingImages.value.some((image) => image.attachmentId && !image.error),
);
const canSubmit = computed(
  () =>
    (inputValue.value.trim() !== '' || hasReadyImage.value) &&
    !isUploadingImage.value,
);

const openImagePicker = () => imageInputRef.value?.click();

const insertEmoji = async (emoji: string) => {
  if (isStreaming.value) {
    return;
  }

  const textarea = textareaRef.value;
  const start = textarea?.selectionStart ?? inputValue.value.length;
  const end = textarea?.selectionEnd ?? inputValue.value.length;

  inputValue.value = [
    inputValue.value.slice(0, start),
    emoji,
    inputValue.value.slice(end),
  ].join('');
  emojiPopoverOpen.value = false;

  await nextTick();

  const nextCursor = start + emoji.length;
  textareaRef.value?.focus({ preventScroll: true });
  textareaRef.value?.setSelectionRange(nextCursor, nextCursor);
};

const handleImageSelected = (event: Event) => {
  const input = event.target as HTMLInputElement;
  const files = Array.from(input.files ?? []).filter((file) =>
    file.type.startsWith('image/'),
  );
  input.value = '';

  for (const file of files) {
    if (pendingImages.value.length >= MAX_IMAGES) {
      break;
    }
    void uploadImage(file);
  }
};

const uploadImage = async (file: File) => {
  const image: PendingImage = {
    id: createMessageId(),
    previewUrl: URL.createObjectURL(file),
    attachmentId: null,
    uploading: true,
    error: false,
  };
  pendingImages.value.push(image);

  try {
    const attachment = await upload(file, {
      purpose: 'conversation_image',
      context: {},
    });
    image.attachmentId = attachment.id;
  } catch (error) {
    image.error = true;
    console.warn('[ai-assistant] 图片上传失败', {
      conversationId: props.conversationId,
      message: resolveAttachmentUploadError(error, t),
      error,
    });
  } finally {
    image.uploading = false;
  }
};

const removePendingImage = (id: string) => {
  const index = pendingImages.value.findIndex((image) => image.id === id);
  if (index === -1) {
    return;
  }
  URL.revokeObjectURL(pendingImages.value[index].previewUrl);
  pendingImages.value.splice(index, 1);
};

const resetConversationState = () => {
  if (isStreaming.value) {
    return;
  }

  for (const message of messages.value) {
    if (message.kind !== 'text') {
      continue;
    }
    for (const url of message.imageUrls ?? []) {
      if (url.startsWith('blob:')) {
        URL.revokeObjectURL(url);
      }
    }
  }
  for (const image of pendingImages.value) {
    URL.revokeObjectURL(image.previewUrl);
  }

  messages.value = [];
  pendingImages.value = [];
  inputValue.value = '';
  emojiPopoverOpen.value = false;
  currentAssistantId = null;
  currentRoundId.value = null;
  currentThreadId.value = null;
  previousThreadId.value = null;
  isPreparingNewThread.value = false;
  isStopping.value = false;
  closeStream();

  nextTick(() => textareaRef.value?.focus());
};

const restorePersistedMessage = (
  message: AiAssistantMessageData,
): ChatMessage[] => {
  if (message.role === 'user') {
    return [
      {
        id: message.id,
        kind: 'text',
        role: 'user',
        content: message.content ?? '',
        attachmentIds:
          message.attachment_ids.length > 0
            ? message.attachment_ids
            : undefined,
        imageUrls:
          message.image_urls.length > 0 ? message.image_urls : undefined,
      },
    ];
  }

  const restored = message.segments.flatMap(
    (segment: AiAssistantMessageSegmentData, index): ChatMessage[] => {
      if (segment.type === 'text') {
        if (!segment.content) {
          throw new Error('Persisted AI text segment is missing its content.');
        }

        return [
          {
            id: `${message.id}-segment-${index}`,
            kind: 'text',
            role: 'assistant',
            content: segment.content,
            pending: false,
          },
        ];
      }

      if (!segment.tool) {
        throw new Error('Persisted AI tool segment is missing its tool name.');
      }

      return [
        {
          id: `${message.id}-segment-${index}`,
          kind: segment.type,
          tool: segment.tool,
          display: segment.tool_display ?? undefined,
          detail: friendlyArgs(
            segment.type === 'tool_call'
              ? (segment.args ?? '')
              : (segment.content ?? ''),
          ),
          expanded: false,
        },
      ];
    },
  );

  if (message.status === 'pending' && restored.length === 0) {
    restored.push({
      id: message.id,
      kind: 'text',
      role: 'assistant',
      content: '',
      pending: true,
    });
  }

  if (message.status === 'failed') {
    const lastMessage = restored[restored.length - 1];
    if (lastMessage?.kind === 'text') {
      lastMessage.error = t('生成失败，请重试');
    } else {
      restored.push({
        id: `${message.id}-error`,
        kind: 'text',
        role: 'assistant',
        content: '',
        pending: false,
        error: t('生成失败，请重试'),
      });
    }
  }

  if (message.status === 'completed' && restored.length === 0) {
    restored.push({
      id: message.id,
      kind: 'text',
      role: 'assistant',
      content: '',
      pending: false,
      error: t('AI 助手暂无回复'),
    });
  }

  return restored;
};

const restorePersistedMessages = async (conversationId: string) => {
  const requestToken = ++historyRequestToken;

  try {
    const response = await axios.get<{
      threads: AiAssistantThreadData[];
    }>(ShowAiAssistantMessagesAction.url(), {
      params: { conversation_id: conversationId },
      headers: { Accept: 'application/json' },
    });

    if (
      requestToken !== historyRequestToken ||
      conversationId !== props.conversationId
    ) {
      return;
    }

    messages.value = response.data.threads.flatMap(
      (thread, threadIndex): ChatMessage[] => {
        const restoredMessages = thread.messages.flatMap(
          restorePersistedMessage,
        );

        if (threadIndex === 0) {
          return restoredMessages;
        }

        return [
          {
            id: `thread-divider-${thread.id}`,
            kind: 'thread_divider',
          },
          ...restoredMessages,
        ];
      },
    );
    currentThreadId.value =
      response.data.threads[response.data.threads.length - 1]?.id ?? null;

    nextTick(() => scrollToBottom());
  } catch (error: unknown) {
    if (requestToken === historyRequestToken) {
      console.warn('[ai-assistant] 历史消息加载失败', {
        conversationId,
        error,
      });
    }
  }
};

const toggleNewThread = () => {
  if (isStreaming.value || !hasMessages.value) {
    return;
  }

  if (isPreparingNewThread.value) {
    const dividerIndex = findLastThreadDividerIndex();
    if (dividerIndex !== -1) {
      messages.value.splice(dividerIndex, 1);
    }
    currentThreadId.value = previousThreadId.value;
    previousThreadId.value = null;
    isPreparingNewThread.value = false;
    return;
  }

  previousThreadId.value = currentThreadId.value;
  currentThreadId.value = null;
  isPreparingNewThread.value = true;
  messages.value.push({
    id: `thread-divider-pending-${createMessageId()}`,
    kind: 'thread_divider',
  });
  nextTick(() => scrollToBottom());
};

// 限制上下文长度，避免单次请求带上过多历史消息。
const MAX_HISTORY_MESSAGES = 20;

const buildHistoryPayload = () => {
  const lastDividerIndex = findLastThreadDividerIndex();

  return messages.value
    .slice(lastDividerIndex + 1)
    .filter((m): m is TextMessage => m.kind === 'text')
    .filter(
      (m) =>
        !m.error &&
        (m.content.trim() !== '' || (m.attachmentIds?.length ?? 0) > 0),
    )
    .slice(-MAX_HISTORY_MESSAGES)
    .map((m) => ({
      role: m.role,
      content: m.content,
      attachment_ids: m.attachmentIds ?? [],
    }));
};

const scrollToBottom = () => {
  const el = messagesRef.value;
  if (!el) {
    return;
  }
  el.scrollTop = el.scrollHeight;
};

const createMessageId = () => crypto.randomUUID();

const closeStream = () => {
  if (currentEventSource) {
    currentEventSource.close();
    currentEventSource = null;
  }
};

const findAssistantById = (id: string | null): TextMessage | undefined => {
  if (!id) return undefined;
  const msg = messages.value.find((m) => m.id === id);
  return msg && msg.kind === 'text' && msg.role === 'assistant'
    ? msg
    : undefined;
};

const openAssistantBubble = (): string => {
  const id = createMessageId();
  messages.value.push({
    id,
    kind: 'text',
    role: 'assistant',
    content: '',
    pending: true,
  });
  currentAssistantId = id;
  nextTick(() => scrollToBottom());
  return id;
};

const sealCurrentAssistantBubble = () => {
  const msg = findAssistantById(currentAssistantId);
  currentAssistantId = null;
  if (!msg) return;

  if (!msg.content.trim()) {
    messages.value = messages.value.filter((m) => m.id !== msg.id);
    return;
  }
  msg.pending = false;
};

const friendlyArgs = (args: string): string => {
  const trimmed = args.trim();
  if (!trimmed) return '';
  try {
    const parsed = JSON.parse(trimmed);
    return JSON.stringify(parsed, null, 2);
  } catch {
    return trimmed;
  }
};

const optionalStreamString = (
  value: unknown,
  field: string,
): string | undefined => {
  if (value === undefined) {
    return undefined;
  }

  if (typeof value !== 'string') {
    throw new Error(`AI stream field "${field}" must be a string.`);
  }

  return value;
};

const requiredStreamString = (value: unknown, field: string): string => {
  const text = optionalStreamString(value, field);
  if (text === undefined || text.trim() === '') {
    throw new Error(`AI stream field "${field}" is required.`);
  }

  return text;
};

const requiredDeltaContent = (value: unknown): string => {
  const text = optionalStreamString(value, 'content');
  if (text === undefined) {
    throw new Error('AI stream field "content" is required.');
  }

  return text;
};

const appendToolChip = (
  kind: 'tool_call' | 'tool_result',
  tool: string,
  display: string | undefined,
  detail: string,
) => {
  messages.value.push({
    id: createMessageId(),
    kind,
    tool,
    display: display && display.trim() ? display : undefined,
    detail,
    expanded: false,
  });
  nextTick(() => scrollToBottom());
};

const toggleToolMessage = (message: ToolMessage) => {
  if (!message.detail.trim()) {
    return;
  }

  message.expanded = !message.expanded;
};

const finalizeStream = (finalErrorMessage?: string) => {
  isStreaming.value = false;
  isStopping.value = false;
  currentRoundId.value = null;
  closeStream();

  if (finalErrorMessage) {
    const existing = findAssistantById(currentAssistantId);
    if (existing) {
      existing.pending = false;
      existing.error = finalErrorMessage;
    } else {
      messages.value.push({
        id: createMessageId(),
        kind: 'text',
        role: 'assistant',
        content: '',
        pending: false,
        error: finalErrorMessage,
      });
    }
  } else {
    const existing = findAssistantById(currentAssistantId);
    if (existing) {
      existing.pending = false;
      if (!existing.content.trim()) {
        existing.error = t('AI 助手暂无回复');
      }
    }
  }

  currentAssistantId = null;
  nextTick(() => {
    scrollToBottom();
    textareaRef.value?.focus();
  });
};

const finalizeStoppedStream = () => {
  isStreaming.value = false;
  isStopping.value = false;
  currentRoundId.value = null;
  closeStream();

  const existing = findAssistantById(currentAssistantId);
  if (existing) {
    existing.pending = false;
    if (!existing.content.trim()) {
      existing.error = t('已停止生成');
    }
  } else {
    messages.value.push({
      id: createMessageId(),
      kind: 'text',
      role: 'assistant',
      content: '',
      pending: false,
      error: t('已停止生成'),
    });
  }

  currentAssistantId = null;
  nextTick(() => {
    scrollToBottom();
    textareaRef.value?.focus();
  });
};

const subscribeToTopic = (roundId: string): void => {
  const source = openMercureEventSource(aiChatTopic(roundId));
  currentEventSource = source;

  const handleStreamEventData = (payload: StreamPayload) => {
    switch (payload.type) {
      case 'delta': {
        if (payload.content === null) {
          return;
        }

        const content = requiredDeltaContent(payload.content);
        let msg = findAssistantById(currentAssistantId);
        if (!msg) {
          openAssistantBubble();
          msg = findAssistantById(currentAssistantId);
        }
        if (msg) {
          msg.content += content;
          nextTick(() => scrollToBottom());
        }
        return;
      }

      case 'tool_call': {
        sealCurrentAssistantBubble();
        appendToolChip(
          'tool_call',
          requiredStreamString(payload.tool, 'tool'),
          optionalStreamString(payload.tool_display, 'tool_display') ??
            undefined,
          friendlyArgs(optionalStreamString(payload.args, 'args') ?? ''),
        );
        return;
      }

      case 'tool_result': {
        appendToolChip(
          'tool_result',
          requiredStreamString(payload.tool, 'tool'),
          optionalStreamString(payload.tool_display, 'tool_display') ??
            undefined,
          friendlyArgs(
            (optionalStreamString(payload.content, 'content') ?? '').trim(),
          ),
        );
        return;
      }

      case 'done': {
        finalizeStream();
        return;
      }

      case 'error': {
        finalizeStream(requiredStreamString(payload.error, 'error'));
        return;
      }

      default:
        throw new Error(`Unsupported AI stream event type: ${payload.type}`);
    }
  };

  source.addEventListener('message', (event) => {
    let eventType: StreamPayload['type'];

    try {
      const payload = JSON.parse(
        (event as MessageEvent<string>).data,
      ) as StreamPayload;
      eventType = payload.type;
      handleStreamEventData(payload);
    } catch (error) {
      console.warn('[ai-assistant] 流式事件格式无效', {
        conversationId: props.conversationId,
        roundId,
        eventType,
        error,
      });
      finalizeStream(t('生成失败，请重试'));
    }
  });

  source.onerror = () => {
    if (source.readyState === EventSource.CLOSED) {
      return;
    }

    console.warn('[ai-assistant] 实时订阅中断', {
      conversationId: props.conversationId,
      roundId,
      readyState: source.readyState,
    });
    finalizeStream(t('AI 助手暂时不可用'));
  };
};

const handleSend = async () => {
  const value = inputValue.value.trim();
  const readyImages = pendingImages.value.filter(
    (image) => image.attachmentId && !image.error,
  );
  const attachmentIds = readyImages.map(
    (image) => image.attachmentId as string,
  );

  if (
    (!value && attachmentIds.length === 0) ||
    isStreaming.value ||
    isUploadingImage.value
  ) {
    return;
  }

  // 先固定历史，避免当前用户消息重复进入 history。
  const historyPayload = buildHistoryPayload();
  if (isPreparingNewThread.value) {
    isPreparingNewThread.value = false;
    previousThreadId.value = null;
  }

  messages.value.push({
    id: createMessageId(),
    kind: 'text',
    role: 'user',
    content: value,
    attachmentIds: attachmentIds.length > 0 ? attachmentIds : undefined,
    imageUrls:
      readyImages.length > 0
        ? readyImages.map((image) => image.previewUrl)
        : undefined,
  });
  inputValue.value = '';
  // 图片消息仍使用当前 object URL，清空待发区时暂不回收。
  pendingImages.value = [];
  nextTick(() => {
    scrollToBottom();
    textareaRef.value?.focus();
  });

  openAssistantBubble();
  isStreaming.value = true;
  isStopping.value = false;

  closeStream();

  const roundId = crypto.randomUUID();
  currentRoundId.value = roundId;

  subscribeToTopic(roundId);

  try {
    const response = await axios.post<{ thread_id: string }>(
      SendAiAssistantMessageAction.url(),
      {
        conversation_id: props.conversationId,
        thread_id: currentThreadId.value,
        prompt: value,
        round_id: roundId,
        history: historyPayload,
        attachment_ids: attachmentIds,
      },
      {
        headers: { Accept: 'application/json' },
      },
    );
    currentThreadId.value = response.data.thread_id;
  } catch (error: unknown) {
    let errorMessage = t('AI 助手暂时不可用');
    if (axios.isAxiosError(error)) {
      const data = error.response?.data as
        { message?: string; errors?: Record<string, string[]> } | undefined;
      if (data?.errors?.prompt?.[0]) {
        errorMessage = data.errors.prompt[0];
      } else if (typeof data?.message === 'string' && data.message) {
        errorMessage = data.message;
      }
    }

    finalizeStream(errorMessage);
  }
};

const handleStop = async () => {
  if (!isStreaming.value || isStopping.value || !currentRoundId.value) {
    return;
  }

  isStopping.value = true;
  const conversationId = props.conversationId;
  const roundId = currentRoundId.value;

  try {
    await axios.post(
      StopAiAssistantMessageAction.url(),
      {
        round_id: roundId,
      },
      {
        headers: { Accept: 'application/json' },
      },
    );

    finalizeStoppedStream();
  } catch (error) {
    isStopping.value = false;
    console.warn('[ai-assistant] 停止生成失败', {
      conversationId,
      roundId,
      error,
    });
  }
};

const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
    event.preventDefault();
    handleSend();
  }
};

// 页面卸载或切换会话时通知后端停止当前流。
const readXsrfTokenFromCookie = (): string | null => {
  if (typeof document === 'undefined') return null;
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  if (!match) return null;
  return decodeURIComponent(match[1]);
};

const fireAndForgetStop = (roundId: string) => {
  if (typeof fetch !== 'function') {
    return;
  }

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  };
  const xsrf = readXsrfTokenFromCookie();
  if (xsrf) {
    headers['X-XSRF-TOKEN'] = xsrf;
  }

  void fetch(StopAiAssistantMessageAction.url(), {
    method: 'POST',
    credentials: 'same-origin',
    keepalive: true,
    headers,
    body: JSON.stringify({ round_id: roundId }),
  }).catch((error: unknown) => {
    console.warn('[ai-assistant] 离开会话时停止生成失败', {
      conversationId: props.conversationId,
      roundId,
      error,
    });
  });
};

onBeforeUnmount(() => {
  if (isStreaming.value && currentRoundId.value) {
    fireAndForgetStop(currentRoundId.value);
  }
  closeStream();
});

watch(
  () => props.conversationId,
  (conversationId, previousConversationId) => {
    if (conversationId === previousConversationId) {
      return;
    }

    if (previousConversationId && isStreaming.value && currentRoundId.value) {
      fireAndForgetStop(currentRoundId.value);
      isStreaming.value = false;
    }

    resetConversationState();
    void restorePersistedMessages(conversationId);
  },
  { immediate: true },
);
</script>

<template>
  <div class="flex h-full min-h-0 w-full flex-col bg-background">
    <div
      class="flex h-10 shrink-0 items-center justify-end border-b border-border/60 px-3"
    >
      <Button
        variant="ghost"
        size="sm"
        class="h-7 px-2 text-xs text-muted-foreground"
        :disabled="!hasMessages || isStreaming"
        :title="isPreparingNewThread ? t('取消新对话') : t('开始一段新对话')"
        @click="toggleNewThread"
      >
        {{ isPreparingNewThread ? t('取消新对话') : t('新对话') }}
      </Button>
    </div>

    <AiAssistantContextBrief
      :contact-profile="contactProfile"
      :target-locale="targetLocale"
      :can-translate="canTranslate"
      :translation-enabled="translationEnabled"
    />

    <div
      ref="messagesRef"
      class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto px-3 py-4"
    >
      <div
        v-if="!hasMessages"
        class="m-auto flex max-w-64 flex-col items-center gap-3 px-4 text-center text-muted-foreground"
      >
        <div
          class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary"
        >
          <svg
            viewBox="2.5 2.5 19 19"
            fill="currentColor"
            class="h-6 w-6"
            aria-hidden="true"
          >
            <path
              fill-rule="evenodd"
              clip-rule="evenodd"
              d="M12 3a9 9 0 0 0-7.74 13.6l-1.21 3.62a.75.75 0 0 0 .95.95l3.62-1.21A9 9 0 1 0 12 3Zm-5.1 9a1.1 1.1 0 1 1 2.2 0 1.1 1.1 0 0 1-2.2 0Zm4 0a1.1 1.1 0 1 1 2.2 0 1.1 1.1 0 0 1-2.2 0Zm4 0a1.1 1.1 0 1 1 2.2 0 1.1 1.1 0 0 1-2.2 0Z"
            />
          </svg>
        </div>
        <div class="space-y-1">
          <p class="text-sm font-medium text-foreground">
            {{ t('需要什么帮助？') }}
          </p>
          <p class="text-xs leading-5">
            {{ t('可以帮你查资料、整理信息或写回复') }}
          </p>
        </div>
      </div>

      <template v-else>
        <template v-for="message in messages" :key="message.id">
          <TextMessageBubble
            v-if="message.kind === 'text'"
            :message="message"
          />
          <div
            v-else-if="message.kind === 'thread_divider'"
            role="separator"
            class="flex items-center gap-2 py-1"
          >
            <div class="h-px flex-1 bg-border" />
            <span class="text-[11px] text-muted-foreground">
              {{ t('新对话') }}
            </span>
            <div class="h-px flex-1 bg-border" />
          </div>
          <ToolMessageChip
            v-else
            :message="message"
            @toggle="toggleToolMessage"
          />
        </template>
      </template>
    </div>

    <div class="shrink-0 border-t border-border/60 bg-background p-3">
      <div
        class="rounded-xl border border-input bg-background px-3 py-2 shadow-xs transition-[box-shadow,border-color] duration-200 focus-within:border-foreground/30 focus-within:shadow-sm"
      >
        <div v-if="pendingImages.length > 0" class="mb-2 flex flex-wrap gap-2">
          <div
            v-for="image in pendingImages"
            :key="image.id"
            class="relative h-14 w-14 overflow-hidden rounded-md border border-input"
          >
            <img
              :src="image.previewUrl"
              alt=""
              :class="[
                'h-full w-full object-cover',
                image.uploading || image.error ? 'opacity-50' : '',
              ]"
            />
            <div
              v-if="image.uploading"
              class="absolute inset-0 flex items-center justify-center"
            >
              <Loader2 class="h-4 w-4 animate-spin text-foreground" />
            </div>
            <div
              v-else-if="image.error"
              class="absolute inset-0 flex items-center justify-center bg-destructive/10"
            >
              <X class="h-4 w-4 text-destructive" />
            </div>
            <button
              type="button"
              class="absolute top-0.5 right-0.5 rounded-full bg-background/80 p-0.5 text-foreground hover:bg-background"
              :aria-label="t('移除图片')"
              @click="removePendingImage(image.id)"
            >
              <X class="h-3 w-3" />
            </button>
          </div>
        </div>
        <textarea
          ref="textareaRef"
          v-model="inputValue"
          rows="2"
          class="max-h-36 min-h-12 w-full resize-none bg-transparent text-sm leading-5 outline-none placeholder:text-muted-foreground"
          :placeholder="t('向 AI 助手提问…')"
          @keydown="handleKeydown"
        />
        <div class="mt-2 flex items-end justify-between gap-2">
          <input
            ref="imageInputRef"
            type="file"
            accept="image/*"
            multiple
            class="hidden"
            @change="handleImageSelected"
          />
          <div class="flex items-center gap-1.5">
            <Popover v-model:open="emojiPopoverOpen">
              <PopoverTrigger as-child>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="size-8 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground disabled:text-muted-foreground/50"
                  :disabled="isStreaming"
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
                      @click="insertEmoji(emoji)"
                    >
                      {{ emoji }}
                    </button>
                  </div>
                </div>
              </PopoverContent>
            </Popover>
            <Button
              size="icon"
              variant="ghost"
              class="h-8 w-8 shrink-0 rounded-lg text-muted-foreground hover:text-foreground"
              :disabled="isStreaming || pendingImages.length >= MAX_IMAGES"
              :aria-label="t('上传图片')"
              @click="openImagePicker"
            >
              <ImagePlus class="h-4 w-4" />
            </Button>
          </div>
          <Button
            size="icon"
            :variant="isStreaming ? 'outline' : 'default'"
            :class="[
              'h-8 w-8 shrink-0 rounded-lg',
              isStreaming
                ? 'border-primary/25 bg-primary/10 text-primary shadow-none hover:bg-primary/15 hover:text-primary'
                : '',
            ]"
            :disabled="isStreaming ? isStopping || !currentRoundId : !canSubmit"
            :aria-label="isStreaming ? t('停止生成') : t('发送')"
            @click="isStreaming ? handleStop() : handleSend()"
          >
            <Loader2
              v-if="isStreaming && isStopping"
              class="h-3.5 w-3.5 animate-spin"
            />
            <Square v-else-if="isStreaming" class="h-3.5 w-3.5 fill-current" />
            <Send v-else class="h-3.5 w-3.5" />
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>
