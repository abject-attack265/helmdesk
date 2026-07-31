<!--
  文件说明：网站渠道独立访客端聊天画布，承接会话状态、消息发送和附件上传交互。
-->
<script setup lang="ts">
import ChannelPausedNotice from '@/components/channel/ChannelPausedNotice.vue';
import StandaloneComposer from '@/components/channel/StandaloneComposer.vue';
import StandaloneMessageBubble from '@/components/channel/StandaloneMessageBubble.vue';
import ImagePreviewDialog from '@/components/common/ImagePreviewDialog.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  type AttachmentPurpose,
  resolveAttachmentUploadError,
  useAttachmentUploader,
} from '@/composables/useAttachmentUploader';
import { useReceptionActivityState } from '@/composables/useReceptionActivityState';
import { formatFileSize } from '@/lib/format';
import {
  type ReceptionConversationPayload,
  subscribeReceptionConversation,
} from '@/lib/mercure';
import {
  STANDALONE_LOCALE_STORAGE_KEY,
  useStandaloneI18n,
} from '@/standalone/i18n';
import { createReceptionClient } from '@/standalone/receptionClient';
import { injectReceptionCredentials } from '@/standalone/receptionCredentials';
import type {
  MessageKind,
  PublicStandaloneChannelData,
  ReceptionActivityStateData,
  ReceptionMessageData,
  ReceptionStateData,
} from '@/types/generated';
import { injectWidgetHostBridge } from '@/widget/useWidgetHostBridge';
import {
  ArrowLeft,
  Image as ImageIcon,
  MessageCircle,
  Paperclip,
  ThumbsDown,
  ThumbsUp,
  User,
  X,
} from '@lucide/vue';
import type { CSSProperties, WatchStopHandle } from 'vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
  defineProps<{
    channel: PublicStandaloneChannelData;
    interactive?: boolean;
    entryMode?: 'standalone' | 'widget';
    // 演示模式：用于后台预览。控件可交互，但不连后端——发消息只在本地回显，便于查看气泡样式。
    demo?: boolean;
  }>(),
  { interactive: false, entryMode: 'standalone', demo: false },
);

// 控件是否可交互：真实联网态或本地演示态都可操作输入区。
const canInteract = computed(() => props.interactive || props.demo);
// 演示模式下的本地消息列表（不落后端）。
const demoMessages = ref<ReceptionMessageData[]>([]);
let demoSeq = 0;

const { locale, t, isSupportedLocale } = useStandaloneI18n();

const widgetHostBridge = injectWidgetHostBridge();
const credentials = injectReceptionCredentials();

// 接待客户端：真实访客入口通过它注入凭证与上下文头。
const receptionClient = credentials
  ? createReceptionClient({
      credentials,
      environmentHeaders: resolveEnvironmentHeaders,
      parseErrorMessage: t('接口返回格式异常'),
      requestErrorMessage: t('发送失败，请稍后重试'),
    })
  : null;

const showWidgetCloseButton = computed(
  () => props.entryMode === 'widget' && widgetHostBridge !== null,
);
const seenNonVisitorMessageIds = new Set<string>();
let widgetUnreadInitialized = false;

function requestWidgetClose(): void {
  widgetHostBridge?.sendToHost('helmdesk:widget:close');
}

const avatarFallback = computed(() => {
  const name = props.channel.assistant_name?.trim();

  if (!name) {
    return 'AI';
  }

  return name.slice(0, 2).toUpperCase();
});

const effectiveGreetingMessage = computed(
  () => props.channel.greeting_message?.trim() ?? '',
);

const hasGreetingContent = computed(
  () => effectiveGreetingMessage.value.length > 0,
);

const state = ref<ReceptionStateData | null>(null);
const loading = ref(false);
const sending = ref(false);
const errorMessage = ref<string | null>(null);
// 乐观渲染：访客点发送即本地插入气泡，不等服务端往返；服务端 echo 回来后按 client_msg_id
// 对账剔除，发送失败则撤掉。
const optimisticMessages = ref<ReceptionMessageData[]>([]);
// 渠道已被管理员软删除且当前访客没有进行中的会话时 /state 会返回 410；
// 已有会话的访客不会触发，仍可继续消息往返。
const pausedWithoutSession = ref(false);
const composerValue = ref('');
const composerRef = ref<InstanceType<typeof StandaloneComposer> | null>(null);
const messageListEl = ref<HTMLDivElement | null>(null);
const attachmentUploading = ref(false);
// CSAT 评价卡：会话关闭后让访客赞/踩 + 选填评论。
const ratingScore = ref<'positive' | 'negative' | null>(null);
const ratingComment = ref('');
const ratingSubmitting = ref(false);
let unsubscribeConversationRealtime: (() => void) | null = null;
let subscribedConversationId: string | null = null;

// 活动租约控制处理提示，消息回源期间保持提示直至最终气泡完成渲染。
const receptionActivityState = useReceptionActivityState();
const agentActivityActive = receptionActivityState.active;
const agentMessageSyncCount = ref(0);
const agentActivityVisible = computed(
  () => agentActivityActive.value || agentMessageSyncCount.value > 0,
);

const MAX_CONTENT_LENGTH = 4000;
const MAX_ATTACHMENT_COUNT = 10;
const WIDGET_HOST_CONTEXT_WAIT_MS = 1000;

type PendingComposerAttachmentStatus = 'uploading' | 'uploaded' | 'failed';

interface PendingComposerAttachment {
  id: string;
  name: string;
  byteSize: number;
  previewUrl: string | null;
  progress: number;
  status: PendingComposerAttachmentStatus;
  statusLabel: string | null;
}

interface PendingComposerUpload {
  id: string;
  conversationId: string | null;
  kind: 'file' | 'image';
  attachments: PendingComposerAttachment[];
  uploadedAttachmentIds: string[];
  clientMsgId: string;
  quotedMessageId: string | null;
}

interface FailedTextSend {
  content: string;
  clientMsgId: string;
  quotedMessageId: string | null;
}

interface ComposerQuoteTarget {
  id: string;
  senderName: string;
  preview: string;
  content: string | null;
  attachments: ReceptionMessageData['attachments'];
}

const pendingUploads = ref<PendingComposerUpload[]>([]);
const composerQuote = ref<ComposerQuoteTarget | null>(null);
const failedTextSend = ref<FailedTextSend | null>(null);
const quotedPreviewOpen = ref(false);
const quotedPreviewImages = ref<ReceptionMessageData['attachments']>([]);
const quotedPreviewInitialId = ref<string | null>(null);
const quotedTextDialogOpen = ref(false);
const quotedTextDialogContent = ref('');
let pendingUploadSequence = 0;

const { upload } = useAttachmentUploader();
// 会话 token 由凭证持有，服务端响应回填后供后续请求带上。
const currentSessionToken = computed(() => {
  if (props.demo) {
    return 'preview';
  }

  return credentials?.sessionToken() ?? '';
});

const isComposerActionDisabled = computed(
  () =>
    !canInteract.value ||
    currentSessionToken.value === '' ||
    sending.value ||
    attachmentUploading.value,
);

const canSend = computed(
  () =>
    canInteract.value &&
    currentSessionToken.value !== '' &&
    !sending.value &&
    !attachmentUploading.value &&
    composerValue.value.trim().length > 0 &&
    composerValue.value.trim().length <= MAX_CONTENT_LENGTH,
);

const visiblePendingUploads = computed(() => {
  const conversationId = state.value?.conversation_id ?? null;

  return pendingUploads.value.filter(
    (upload) => upload.conversationId === conversationId,
  );
});

const messages = computed<ReceptionMessageData[]>(() => {
  if (props.demo) {
    return demoMessages.value;
  }

  const serverMessages = state.value
    ? normalizeReceptionMessages(state.value.messages)
    : [];

  return optimisticMessages.value.length === 0
    ? serverMessages
    : [...serverMessages, ...unreconciledOptimisticMessages()];
});

// 跟随 <html> 的 .dark class（由 initializeTheme 依据浏览器 prefers-color-scheme
// 或访客已存外观决定）。pageStyle 里的渐变与气泡色是 JS 计算的，必须显式感知深色态，
// 否则深色模式下整页仍是写死的白色渐变，只有用 CSS 变量的文字会翻转，导致半适配。
// setup 阶段同步读取（blade 内联脚本已在渲染前打好 .dark），保证首屏不闪。
const isDark = ref(
  typeof document !== 'undefined' &&
    document.documentElement.classList.contains('dark'),
);
let themeClassObserver: MutationObserver | null = null;

function syncIsDark(): void {
  isDark.value = document.documentElement.classList.contains('dark');
}

// 统一主题色驱动整套渐变背景与气泡视觉，背景始终是渐变。
// 渐变必须落在 background 上（Tailwind 的 bg-* 会落到 background-color，无法承载渐变）。
// 深色态保留商户品牌色，仅把白底换成深底（与浅色态“品牌色 + 白”对称）。
const pageStyle = computed<CSSProperties>(() => {
  const themeColor = props.channel.theme_color;
  // 深底用 oklch(0.145 0 0)，与 app.css 深色 --background 及 blade html.dark 背景对齐。
  const base = isDark.value ? 'oklch(0.145 0 0)' : '#ffffff';
  const background = isDark.value
    ? `linear-gradient(160deg, color-mix(in srgb, ${themeColor} 24%, ${base}), color-mix(in srgb, ${themeColor} 10%, ${base}) 45%, ${base} 100%)`
    : `linear-gradient(160deg, color-mix(in srgb, ${themeColor} 20%, ${base}), color-mix(in srgb, ${themeColor} 7%, ${base}) 45%, ${base} 100%)`;

  return {
    '--standalone-primary': themeColor,
    '--standalone-background': background,
    // AI 气泡：深色态用半透明白做磨砂浮起面 + 近白字，配深底渐变可读；浅色态维持白底深字。
    '--standalone-assistant-bubble': isDark.value
      ? 'rgba(255, 255, 255, 0.08)'
      : '#ffffff',
    '--standalone-assistant-text': isDark.value ? '#f5f5f5' : '#111827',
    '--standalone-visitor-bubble': themeColor,
    '--standalone-visitor-text': '#ffffff',
    background,
  } as CSSProperties;
});

// 首页态：home_mode_enabled 时访客先看到欢迎屏，点击「进入聊天」再切到 thread。
const activeView = ref<'home' | 'thread'>(
  props.channel.home_mode_enabled ? 'home' : 'thread',
);

const homeWelcomeMessage = computed(
  () => props.channel.home_welcome_message?.trim() ?? '',
);

// 首页续聊卡片的副文案：优先用欢迎语，缺省退回副标题。
const homeCardHint = computed(
  () => effectiveGreetingMessage.value || props.channel.subtitle?.trim() || '',
);

function enterChat(): void {
  activeView.value = 'thread';
  void focusComposer();
}

function backToHome(): void {
  if (props.channel.home_mode_enabled) {
    activeView.value = 'home';
  }
}

// thread 态顶部是否展示悬浮胶囊标题栏：标题栏开启或处于首页模式（需要返回入口）时展示。
const showThreadHeader = computed(
  () => props.channel.header.enabled || props.channel.home_mode_enabled,
);

const enabledSuggestionItems = computed(() => {
  if (!props.channel.suggestions.enabled) {
    return [];
  }

  return props.channel.suggestions.items
    .map((item) => item.trim())
    .filter(Boolean)
    .slice(0, 6);
});

function normalizeReceptionMessages(value: unknown): ReceptionMessageData[] {
  if (Array.isArray(value)) {
    return value as ReceptionMessageData[];
  }

  throw new Error(t('接口返回格式异常'));
}

const showInlineGreeting = computed(() => {
  if (props.demo) {
    return demoMessages.value.length === 0;
  }

  if (!props.interactive) {
    return true;
  }

  // 服务端状态未返回且存在乐观气泡时隐藏欢迎语。
  if (state.value === null) {
    return optimisticMessages.value.length === 0;
  }

  return messages.value.length === 0;
});

// 演示模式：本地追加一条消息，仅用于在预览里展示气泡样式，不落后端。
function appendDemoMessage(
  role: 'visitor' | 'ai',
  content: string,
  options: {
    kind?: MessageKind;
    attachments?: ReceptionMessageData['attachments'];
  } = {},
): void {
  demoSeq += 1;
  demoMessages.value.push({
    id: `preview-${demoSeq}`,
    role,
    kind: options.kind ?? 'text',
    content,
    sender_name: null,
    sender_avatar_url: null,
    created_at: new Date().toISOString(),
    seq_no: demoSeq,
    client_msg_id: null,
    delivery_status: 'sent',
    quoted_message_id: null,
    quoted_message: null,
    recalled_at: null,
    recalled_content: null,
    attachments: options.attachments ?? [],
  });
}

// 演示模式：把选中的文件转成可本地预览的附件数据（图片用 object URL 直接预览，文件给下载入口）。
function buildDemoAttachments(
  files: File[],
  kind: 'file' | 'image',
): ReceptionMessageData['attachments'] {
  return files.map((file, index) => {
    const objectUrl =
      typeof URL !== 'undefined' ? URL.createObjectURL(file) : '';
    const isImage = file.type.startsWith('image/');

    return {
      id: `preview-att-${demoSeq}-${pendingUploadSequence++}-${index}`,
      name: file.name || `${kind}-${index + 1}`,
      mime_type:
        file.type || (isImage ? 'image/png' : 'application/octet-stream'),
      byte_size: file.size,
      url: objectUrl,
    };
  });
}

// 演示模式回收本地附件创建的 object URL，避免预览反复重置后内存泄漏。
function clearDemoMessages(): void {
  if (typeof URL !== 'undefined') {
    for (const message of demoMessages.value) {
      for (const attachment of message.attachments ?? []) {
        if (attachment.url.startsWith('blob:')) {
          URL.revokeObjectURL(attachment.url);
        }
      }
    }
  }

  demoMessages.value = [];
}

// 接待请求统一走接待客户端，并把响应里的会话 token 回填到凭证。
async function callApi(
  method: 'GET' | 'POST',
  path: string,
  body?: unknown,
): Promise<ReceptionStateData> {
  if (!receptionClient) {
    throw new Error(t('接口返回格式异常'));
  }

  const next = await receptionClient.request<ReceptionStateData>(
    method,
    path,
    body,
  );
  credentials?.rememberSessionToken(next.session_token);

  return next;
}

// 可评价（会话已关闭，或 AI 会话空闲被软邀请）且尚未评价时展示评价卡；已评价则展示「感谢」态。
const showRatingPrompt = computed(
  () => props.interactive && state.value?.can_rate === true,
);
const submittedRating = computed(() => state.value?.rating ?? null);

// 提交访客评价；成功后用刷新的接待状态翻成「已评价」态。
async function submitRating(): Promise<void> {
  if (
    !props.interactive ||
    ratingScore.value === null ||
    ratingSubmitting.value
  ) {
    return;
  }

  ratingSubmitting.value = true;
  errorMessage.value = null;
  try {
    state.value = await callApi(
      'POST',
      `/api/chat/${encodeURIComponent(props.channel.code)}/rating`,
      {
        score: ratingScore.value,
        comment: ratingComment.value.trim() || null,
      },
    );
    ratingScore.value = null;
    ratingComment.value = '';
  } catch (err) {
    errorMessage.value = resolveAttachmentUploadError(err, t);
  } finally {
    ratingSubmitting.value = false;
  }
}

// 访客环境头承载 locale/timezone，入口形态与业务 query 参数由接待客户端按凭证注入。
function resolveEnvironmentHeaders(): Record<string, string> {
  const headers: Record<string, string> = {};
  const visitorLocale = resolveVisitorLocale();
  const visitorTimezone = resolveVisitorTimezone();

  if (visitorLocale) {
    headers['X-Helmdesk-Visitor-Locale'] = visitorLocale;
  }
  if (visitorTimezone) {
    headers['X-Helmdesk-Visitor-Timezone'] = visitorTimezone;
  }

  return headers;
}

function resolveVisitorLocale(): string | null {
  if (typeof navigator !== 'undefined') {
    const browserLocale = [
      ...(navigator.languages ?? []),
      navigator.language,
    ].find((value): value is string => Boolean(value?.trim()));

    if (browserLocale) {
      return browserLocale;
    }
  }

  if (typeof window !== 'undefined') {
    const storedLocale = window.localStorage.getItem(
      STANDALONE_LOCALE_STORAGE_KEY,
    );
    if (isSupportedLocale(storedLocale)) {
      return storedLocale;
    }
  }

  return locale.value || null;
}

function resolveVisitorTimezone(): string | null {
  return Intl.DateTimeFormat().resolvedOptions().timeZone || null;
}

async function loadState(): Promise<void> {
  if (!props.interactive) {
    return;
  }

  loading.value = true;
  errorMessage.value = null;
  try {
    await waitForInitialWidgetHostContext();
    state.value = await callApi(
      'GET',
      `/api/chat/${encodeURIComponent(props.channel.code)}/state`,
    );
    pausedWithoutSession.value = false;
    await scrollToBottom();
  } catch (err) {
    // 410 = 渠道已暂停且当前访客没有可恢复的会话；展示 paused 占位而不是错误。
    if (
      props.channel.paused &&
      typeof err === 'object' &&
      err !== null &&
      (err as { status?: number }).status === 410
    ) {
      pausedWithoutSession.value = true;
      return;
    }
    errorMessage.value = resolveAttachmentUploadError(err, t);
  } finally {
    loading.value = false;
  }
}

async function waitForInitialWidgetHostContext(): Promise<void> {
  if (
    props.entryMode !== 'widget' ||
    !widgetHostBridge ||
    widgetHostBridge.hostContext.value !== null
  ) {
    return;
  }

  await new Promise<void>((resolve) => {
    let stop: WatchStopHandle | null = null;
    const timer = window.setTimeout(() => {
      stop?.();
      resolve();
    }, WIDGET_HOST_CONTEXT_WAIT_MS);

    stop = watch(
      () => widgetHostBridge.hostContext.value,
      (value) => {
        if (value === null) {
          return;
        }

        window.clearTimeout(timer);
        stop?.();
        resolve();
      },
      { flush: 'sync' },
    );
  });
}

// 收到"会话已变更"信号后回源拉取最新会话（不走 loading 占位，避免每条消息刷新都闪烁）。
async function reloadVisitorState(): Promise<void> {
  try {
    state.value = await callApi(
      'GET',
      `/api/chat/${encodeURIComponent(props.channel.code)}/state`,
    );
    await scrollToBottom();
  } catch (err) {
    errorMessage.value = err instanceof Error ? err.message : String(err);
  }
}

async function sendMessage(): Promise<void> {
  if (!canSend.value) {
    return;
  }

  const content = composerValue.value.trim();

  // 演示模式：本地回显访客消息并给一条示例回复，纯前端、不发后端。
  if (props.demo) {
    appendDemoMessage('visitor', content);
    composerValue.value = '';
    clearComposerQuote();
    await scrollToBottom();
    await focusComposer();
    window.setTimeout(() => {
      appendDemoMessage('ai', t('这是一条预览示例回复，仅用于查看气泡样式。'));
      void scrollToBottom();
    }, 400);

    return;
  }

  const quotedMessageId = composerQuote.value?.id ?? null;
  const failedSend = failedTextSend.value;
  const clientMsgId =
    failedSend?.content === content &&
    failedSend.quotedMessageId === quotedMessageId
      ? failedSend.clientMsgId
      : generateClientMsgId();
  // 保存引用，发送失败时恢复输入状态。
  const previousQuote = composerQuote.value;

  // 先渲染本地消息，服务端响应后按 client_msg_id 对账。
  optimisticMessages.value.push(
    buildOptimisticVisitorMessage(content, clientMsgId),
  );
  composerValue.value = '';
  clearComposerQuote();
  await scrollToBottom();

  sending.value = true;
  errorMessage.value = null;
  try {
    await postMessage(content, [], clientMsgId, quotedMessageId);
    failedTextSend.value = null;
    await scrollToBottom();
  } catch (err) {
    // 恢复正文、引用和本次发送的幂等键。
    removeOptimisticMessage(clientMsgId);
    composerValue.value = content;
    composerQuote.value = previousQuote;
    failedTextSend.value = { content, clientMsgId, quotedMessageId };
    errorMessage.value = err instanceof Error ? err.message : String(err);
  } finally {
    sending.value = false;
    await focusComposer();
  }
}

// 构造一条仅用于本地即时显示的访客文本消息；client_msg_id 作为与服务端 echo 对账的键。
function buildOptimisticVisitorMessage(
  content: string,
  clientMsgId: string,
): ReceptionMessageData {
  return {
    id: `optimistic-${clientMsgId}`,
    role: 'visitor',
    kind: 'text',
    content,
    sender_name: null,
    sender_avatar_url: null,
    created_at: new Date().toISOString(),
    seq_no: Number.MAX_SAFE_INTEGER,
    client_msg_id: clientMsgId,
    delivery_status: 'sending',
    quoted_message_id: null,
    quoted_message: null,
    recalled_at: null,
    recalled_content: null,
    attachments: [],
  };
}

// 仍未被服务端回传对账的乐观气泡（其 client_msg_id 尚未出现在已落库消息里）。
function unreconciledOptimisticMessages(): ReceptionMessageData[] {
  const serverClientMsgIds = new Set(
    (state.value?.messages ?? [])
      .map((message) => message.client_msg_id)
      .filter((id): id is string => id !== null),
  );

  return optimisticMessages.value.filter(
    (message) => !serverClientMsgIds.has(message.client_msg_id ?? ''),
  );
}

// 发送失败时按 client_msg_id 移除对应的乐观气泡。
function removeOptimisticMessage(clientMsgId: string): void {
  optimisticMessages.value = optimisticMessages.value.filter(
    (message) => message.client_msg_id !== clientMsgId,
  );
}

// 访客「正在输入」信号上报节流：连续输入期间最多每 2.5s 发一帧，远低于每次按键。
// 服务端据此推迟聚合 flush，让访客一句话拆几段连发时 AI 等打完再回，而不是逐句作答。
const TYPING_NOTIFY_THROTTLE_MS = 2500;
let lastTypingNotifiedAt = 0;

// handleComposerInput 在访客实际输入时按节流上报 typing 信号（仅交互态、非演示模式）。
// @input 仅由真实输入触发，发送后的程序化清空不会误报。
function handleComposerInput(): void {
  if (props.demo || !props.interactive || !receptionClient) {
    return;
  }
  if (composerValue.value.trim() === '') {
    return;
  }

  const now = Date.now();
  if (now - lastTypingNotifiedAt < TYPING_NOTIFY_THROTTLE_MS) {
    return;
  }
  lastTypingNotifiedAt = now;

  void receptionClient.notifyTyping(
    `/api/chat/${encodeURIComponent(props.channel.code)}/typing`,
  );
}

function generateClientMsgId(): string {
  return crypto.randomUUID();
}

async function postMessage(
  content: string,
  attachmentIds: string[],
  clientMsgId: string = generateClientMsgId(),
  quotedMessageId: string | null = composerQuote.value?.id ?? null,
): Promise<void> {
  state.value = await callApi(
    'POST',
    `/api/chat/${encodeURIComponent(props.channel.code)}/messages`,
    {
      content,
      attachment_ids: attachmentIds,
      client_msg_id: clientMsgId,
      quoted_message_id: quotedMessageId,
    },
  );
}

async function recallMessage(messageId: string): Promise<void> {
  if (!messageId) {
    return;
  }
  // 二次校验：菜单可能悬停了一段时间才点；以点击瞬间的时间为准重新核对 2 分钟窗口。
  const target = messages.value.find((item) => item.id === messageId);
  if (target && !isMessageRecallable(target, Date.now())) {
    return;
  }

  errorMessage.value = null;
  try {
    state.value = await callApi(
      'POST',
      `/api/chat/${encodeURIComponent(props.channel.code)}/messages/${encodeURIComponent(messageId)}/recall`,
    );
    await scrollToBottom();
  } catch (err) {
    errorMessage.value = err instanceof Error ? err.message : String(err);
  }
}

// 撤回 2 分钟时效窗口。不维护实时时钟：菜单打开时取一次时间快照即可，
// 多条消息共用同一个 ref（同时只会有一个右键菜单展开）。
const RECALL_WINDOW_MS = 2 * 60 * 1000;
const contextMenuOpenedAt = ref<number>(0);

function messageCreatedMs(message: ReceptionMessageData): number | null {
  const ts = Date.parse(message.created_at);

  return Number.isNaN(ts) ? null : ts;
}

// 乐观气泡尚未真正落库（id 用 client_msg_id 占位），其 id 不能用于撤回/引用等服务端操作。
function isOptimisticMessage(message: ReceptionMessageData): boolean {
  return message.id.startsWith('optimistic-');
}

function isMessageRecallable(
  message: ReceptionMessageData,
  referenceNow: number = Date.now(),
): boolean {
  if (!props.interactive) {
    return false;
  }
  if (!isVisitorMessage(message.role)) {
    return false;
  }
  if (isOptimisticMessage(message)) {
    return false;
  }
  if (message.recalled_at) {
    return false;
  }
  const created = messageCreatedMs(message);
  if (created === null) {
    return false;
  }

  return referenceNow - created <= RECALL_WINDOW_MS;
}

function handleContextMenuOpenChange(open: boolean): void {
  if (open) {
    contextMenuOpenedAt.value = Date.now();
  }
}

function canCopyMessage(message: ReceptionMessageData): boolean {
  return (
    !message.recalled_at &&
    typeof message.content === 'string' &&
    message.content.length > 0
  );
}

async function copyMessageContent(
  message: ReceptionMessageData,
): Promise<void> {
  if (!canCopyMessage(message)) {
    return;
  }
  try {
    await navigator.clipboard.writeText(message.content);
  } catch {
    return;
  }
}

function quoteMessage(message: ReceptionMessageData): void {
  if (message.recalled_at || isOptimisticMessage(message)) {
    return;
  }

  composerQuote.value = {
    id: message.id,
    senderName: senderLabel(message),
    preview: quotePreview(message),
    content: message.content,
    attachments: message.attachments ?? [],
  };
  void focusComposer();
}

function clearComposerQuote(): void {
  composerQuote.value = null;
}

function quotePreview(message: ReceptionMessageData): string {
  if (
    typeof message.content === 'string' &&
    message.content.trim().length > 0
  ) {
    return message.content.replace(/\s+/g, ' ').slice(0, 120);
  }
  if (message.kind === 'image') {
    return t('图片');
  }
  if (message.kind === 'file') {
    return t('文件');
  }

  return t('无内容');
}

type QuotedMessage = NonNullable<ReceptionMessageData['quoted_message']>;
type ReceptionAttachment = ReceptionMessageData['attachments'][number];

function normalizeQuotedAttachments(value: unknown): ReceptionAttachment[] {
  if (Array.isArray(value)) {
    return value as ReceptionAttachment[];
  }
  if (value && typeof value === 'object') {
    return Object.values(value) as ReceptionAttachment[];
  }

  return [];
}

function quotedFullContent(
  quoted: QuotedMessage | ComposerQuoteTarget,
): string {
  const content = quoted.content?.trim();
  if (content) {
    return content;
  }

  return quoted.preview || t('无内容');
}

function quotedImageAttachment(
  quoted: QuotedMessage | ComposerQuoteTarget,
): ReceptionAttachment | null {
  return (
    normalizeQuotedAttachments(quoted.attachments).find((attachment) =>
      attachment.mime_type.startsWith('image/'),
    ) ?? null
  );
}

function quotedFileAttachment(
  quoted: QuotedMessage | ComposerQuoteTarget,
): ReceptionAttachment | null {
  return (
    normalizeQuotedAttachments(quoted.attachments).find(
      (attachment) => !attachment.mime_type.startsWith('image/'),
    ) ?? null
  );
}

function openQuotedImage(image: ReceptionAttachment): void {
  quotedPreviewImages.value = [image];
  quotedPreviewInitialId.value = image.id;
  quotedPreviewOpen.value = true;
}

function openQuotedFile(file: ReceptionAttachment): void {
  window.open(file.url, '_blank', 'noopener,noreferrer');
}

// 图片走 ImagePreviewDialog、文件走新标签打开、纯文本走居中模态框。
// 用模态框而非 Popover：指向尖头的 CSS 在 reka-ui Portal 上下文里有颜色/渲染异常，
// 且模态框在移动端小屏上点击区域更友好。
function openQuotedTarget(quoted: QuotedMessage | ComposerQuoteTarget): void {
  const image = quotedImageAttachment(quoted);
  if (image) {
    openQuotedImage(image);
    return;
  }
  const file = quotedFileAttachment(quoted);
  if (file) {
    openQuotedFile(file);
    return;
  }
  quotedTextDialogContent.value = quotedFullContent(quoted);
  quotedTextDialogOpen.value = true;
}

function reeditRecalledMessage(message: ReceptionMessageData): void {
  const content = message.recalled_content;
  if (typeof content !== 'string' || content.length === 0) {
    return;
  }
  const existing = composerValue.value;
  composerValue.value =
    existing.length === 0
      ? content
      : existing.endsWith('\n')
        ? existing + content
        : `${existing}\n${content}`;
  void nextTick().then(() => {
    void focusComposer();
  });
}

// 输入区文件/图片选择回调：取文件、清空 input 以便再次选同名文件，再走上传发送。
async function handleComposerFileSelect(
  event: Event,
  kind: 'file' | 'image',
): Promise<void> {
  const target = event.target as HTMLInputElement;
  const files = Array.from(target.files ?? []);
  target.value = '';

  await uploadAndSendAttachments(
    files,
    kind === 'image' ? 'conversation_image' : 'conversation_file',
    kind,
  );
}

function validateAttachmentFiles(files: File[]): boolean {
  if (files.length === 0) {
    return false;
  }

  if (files.length > MAX_ATTACHMENT_COUNT) {
    errorMessage.value = t('一次最多发送 {count} 个附件', {
      count: MAX_ATTACHMENT_COUNT,
    });

    return false;
  }

  return true;
}

async function uploadAndSendAttachments(
  files: File[],
  purpose: AttachmentPurpose,
  kind: 'file' | 'image',
): Promise<void> {
  if (isComposerActionDisabled.value && !attachmentUploading.value) {
    return;
  }
  if (!validateAttachmentFiles(files)) return;

  // 演示模式：本地回显附件消息并给一条示例回复，纯前端、不发后端。
  if (props.demo) {
    appendDemoMessage('visitor', '', {
      kind,
      attachments: buildDemoAttachments(files, kind),
    });
    clearComposerQuote();
    await scrollToBottom();
    await focusComposer();
    window.setTimeout(() => {
      appendDemoMessage('ai', t('这是一条预览示例回复，仅用于查看气泡样式。'));
      void scrollToBottom();
    }, 400);

    return;
  }

  let sessionToken: string;
  try {
    sessionToken = await ensureSessionState();
  } catch (err) {
    errorMessage.value = err instanceof Error ? err.message : String(err);

    return;
  }

  const conversationId = state.value?.conversation_id ?? null;

  const pendingUpload = createPendingUpload(conversationId, files, kind);
  pendingUploads.value.push(pendingUpload);

  attachmentUploading.value = true;
  errorMessage.value = null;
  void scrollToBottom();

  try {
    const uploadedAttachmentIds: string[] = [];

    for (const [index, file] of files.entries()) {
      const pendingAttachment = pendingUpload.attachments[index];
      const attachment = await upload(file, {
        purpose,
        scope: 'visitor',
        context: {
          channel_code: props.channel.code,
        },
        visitorToken: sessionToken,
        visitorUserToken: credentials?.userToken() ?? null,
        onProgress: (value) => {
          pendingAttachment.progress = Math.min(100, Math.max(0, value));
        },
      });

      pendingAttachment.name = attachment.name;
      pendingAttachment.byteSize = attachment.byte_size;
      pendingAttachment.progress = 100;
      pendingAttachment.status = 'uploaded';
      uploadedAttachmentIds.push(attachment.id);
      pendingUpload.uploadedAttachmentIds = [...uploadedAttachmentIds];
    }

    attachmentUploading.value = false;
    sending.value = true;
    try {
      await postMessage(
        '',
        uploadedAttachmentIds,
        pendingUpload.clientMsgId,
        pendingUpload.quotedMessageId,
      );
      clearComposerQuote();
      removePendingUpload(pendingUpload.id);
      await scrollToBottom();
    } catch (err) {
      markPendingUploadFailed(pendingUpload.id, t('发送失败'), true);
      errorMessage.value = err instanceof Error ? err.message : String(err);
    } finally {
      sending.value = false;
      await focusComposer();
    }
  } catch (err) {
    errorMessage.value = resolveAttachmentUploadError(
      err,
      t,
      kind === 'image' ? '图片上传失败' : '附件上传失败',
    );
    markPendingUploadFailed(pendingUpload.id, t('上传失败'));
  } finally {
    attachmentUploading.value = false;
  }
}

function createPendingUpload(
  conversationId: string | null,
  files: File[],
  kind: 'file' | 'image',
): PendingComposerUpload {
  const uploadId = `composer-upload-${Date.now()}-${pendingUploadSequence++}`;

  return {
    id: uploadId,
    conversationId,
    kind,
    uploadedAttachmentIds: [],
    clientMsgId: generateClientMsgId(),
    quotedMessageId: composerQuote.value?.id ?? null,
    attachments: files.map((file, index) => ({
      id: `${uploadId}-${index}`,
      name: file.name || `${kind}-${index + 1}`,
      byteSize: file.size,
      previewUrl:
        kind === 'image' && typeof URL !== 'undefined'
          ? URL.createObjectURL(file)
          : null,
      progress: 0,
      status: 'uploading',
      statusLabel: null,
    })),
  };
}

function canRetryPendingUpload(pendingUpload: PendingComposerUpload): boolean {
  return (
    pendingUpload.uploadedAttachmentIds.length ===
    pendingUpload.attachments.length
  );
}

async function retryPendingUpload(
  pendingUpload: PendingComposerUpload,
): Promise<void> {
  if (!canRetryPendingUpload(pendingUpload) || sending.value) {
    return;
  }

  sending.value = true;
  errorMessage.value = null;
  try {
    await postMessage(
      '',
      pendingUpload.uploadedAttachmentIds,
      pendingUpload.clientMsgId,
      pendingUpload.quotedMessageId,
    );
    removePendingUpload(pendingUpload.id);
    clearComposerQuote();
    await scrollToBottom();
  } catch (err) {
    errorMessage.value = err instanceof Error ? err.message : String(err);
  } finally {
    sending.value = false;
    await focusComposer();
  }
}

function markPendingUploadFailed(
  pendingUploadId: string,
  label: string,
  includeUploaded = false,
): void {
  const pendingUpload = pendingUploads.value.find(
    (upload) => upload.id === pendingUploadId,
  );
  if (!pendingUpload) return;

  for (const attachment of pendingUpload.attachments) {
    if (includeUploaded || attachment.status !== 'uploaded') {
      attachment.status = 'failed';
      attachment.statusLabel = label;
    }
  }
}

function removePendingUpload(pendingUploadId: string): void {
  const pendingUpload = pendingUploads.value.find(
    (upload) => upload.id === pendingUploadId,
  );
  if (pendingUpload) {
    revokePendingUploadPreviews(pendingUpload);
  }

  pendingUploads.value = pendingUploads.value.filter(
    (upload) => upload.id !== pendingUploadId,
  );
}

function clearPendingUploads(): void {
  for (const pendingUpload of pendingUploads.value) {
    revokePendingUploadPreviews(pendingUpload);
  }

  pendingUploads.value = [];
}

function revokePendingUploadPreviews(
  pendingUpload: PendingComposerUpload,
): void {
  if (typeof URL === 'undefined') return;

  for (const attachment of pendingUpload.attachments) {
    if (attachment.previewUrl?.startsWith('blob:')) {
      URL.revokeObjectURL(attachment.previewUrl);
    }
  }
}

function pendingAttachmentStatusLabel(
  attachment: PendingComposerAttachment,
): string {
  if (attachment.status === 'failed') {
    return attachment.statusLabel ?? t('上传失败');
  }

  return `${attachment.progress}%`;
}

async function ensureSessionState(): Promise<string> {
  if (currentSessionToken.value !== '') {
    return currentSessionToken.value;
  }

  await loadState();

  if (currentSessionToken.value !== '') {
    return currentSessionToken.value;
  }

  throw new Error(t('会话尚未准备好，请稍后重试'));
}

function handleComposerPaste(event: ClipboardEvent): void {
  if (isComposerActionDisabled.value) return;

  const imageFiles = pastedImageFiles(event);
  if (imageFiles.length === 0) return;

  event.preventDefault();
  void uploadAndSendAttachments(imageFiles, 'conversation_image', 'image');
}

function pastedImageFiles(event: ClipboardEvent): File[] {
  const items = Array.from(event.clipboardData?.items ?? []);

  return items
    .filter((item) => item.kind === 'file' && item.type.startsWith('image/'))
    .map((item, index) => {
      const file = item.getAsFile();

      return file ? normalizePastedImageFile(file, index) : null;
    })
    .filter((file): file is File => file !== null);
}

function normalizePastedImageFile(file: File, index: number): File {
  if (file.name) {
    return file;
  }

  return new File([file], `pasted-image-${Date.now()}-${index + 1}.png`, {
    type: file.type || 'image/png',
  });
}

async function sendSuggestedQuestion(question: string): Promise<void> {
  if (isComposerActionDisabled.value) {
    return;
  }

  composerValue.value = question;
  await nextTick();
  await sendMessage();
}

async function focusComposer(): Promise<void> {
  if (!canInteract.value) {
    return;
  }

  await nextTick();

  // 右键 ContextMenu 关闭时会把焦点 restore 回 trigger 元素，要等下一帧才能抢回到 textarea。
  if (typeof window === 'undefined') {
    composerRef.value?.focus();
    return;
  }

  window.requestAnimationFrame(() => {
    composerRef.value?.focus();
  });
}

async function scrollToBottom(): Promise<void> {
  await nextTick();
  const el = messageListEl.value;
  if (!el) {
    return;
  }

  el.scrollTop = el.scrollHeight;
}

function formatDateTime(iso: string): string {
  const date = new Date(iso);

  if (Number.isNaN(date.getTime())) {
    throw new Error(`Invalid message timestamp: ${iso}`);
  }

  return date.toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  });
}

function isVisitorMessage(role: string): boolean {
  return role === 'visitor';
}

function senderLabel(message: ReceptionMessageData): string {
  if (message.sender_name) {
    return message.sender_name;
  }

  if (message.role === 'ai') {
    return props.channel.assistant_name;
  }

  if (message.role === 'teammate') {
    return t('客服');
  }

  if (message.role === 'visitor') {
    return t('我');
  }

  return '';
}

watch(
  () => props.interactive,
  (next) => {
    if (next) {
      void loadState();
    }

    if (!next) {
      closeConversationEventSource();
    }
  },
);

function closeConversationEventSource(): void {
  if (unsubscribeConversationRealtime) {
    unsubscribeConversationRealtime();
    unsubscribeConversationRealtime = null;
  }
  subscribedConversationId = null;
  clearAgentActivity();
}

function subscribeConversationRealtime(conversationId: string): void {
  if (!props.interactive || subscribedConversationId === conversationId) {
    return;
  }

  closeConversationEventSource();
  subscribedConversationId = conversationId;

  unsubscribeConversationRealtime = subscribeReceptionConversation(
    conversationId,
    {
      onUpdate: (payload) => {
        void handleConversationRealtimeUpdate(payload);
      },
      onAgentActivity: (payload) => {
        applyAgentActivity(payload);
      },
    },
  );
}

// 接待方消息回源期间保留处理提示，消息完成渲染后再结束视觉交接。
async function handleConversationRealtimeUpdate(
  payload: ReceptionConversationPayload,
): Promise<void> {
  const bridgesAgentMessage =
    agentActivityVisible.value &&
    (payload.event === 'ai_message_created' ||
      payload.event === 'teammate_message_created');

  if (bridgesAgentMessage) {
    agentMessageSyncCount.value++;
  }

  try {
    await reloadVisitorState();
  } finally {
    if (bridgesAgentMessage) {
      agentMessageSyncCount.value = Math.max(
        0,
        agentMessageSyncCount.value - 1,
      );
    }
  }
}

// 新活动开始时保持消息列表位于底部。
function applyAgentActivity(activity: ReceptionActivityStateData): void {
  if (receptionActivityState.apply(activity) && activity.active) {
    void scrollToBottom();
  }
}

function clearAgentActivity(): void {
  receptionActivityState.reset();
  agentMessageSyncCount.value = 0;
}

watch(
  () => state.value?.agent_activity,
  (activity) => {
    if (activity) {
      applyAgentActivity(activity);
    }
  },
);

// 服务端消息更新（POST 响应或实时 echo 回源）后，剔除已对账的乐观气泡，避免无限累积。
watch(
  () => state.value?.messages,
  () => {
    optimisticMessages.value = unreconciledOptimisticMessages();
  },
);

watch(
  () => state.value?.conversation_id ?? null,
  (conversationId) => {
    clearComposerQuote();

    if (conversationId) {
      subscribeConversationRealtime(conversationId);
    } else {
      closeConversationEventSource();
    }
  },
);

// 仅在嵌入 widget 时，把"访客没看见过的非访客消息"折算成未读和 toast 推给宿主页：
//   - 首次加载视为已读，避免刷新页面闪一堆 toast；
//   - host 端报告 visible=true 时清零未读；
//   - 新到达的非访客消息 → 累计未读 + 用最近一条做 toast。
watch(
  [
    () => state.value?.messages ?? [],
    () => widgetHostBridge?.hostVisible.value ?? null,
  ],
  ([nextMessages, visible]) => {
    if (!widgetHostBridge) {
      return;
    }

    const nonVisitor = nextMessages.filter(
      (message) => message.role !== 'visitor',
    );
    const nonVisitorIds = nonVisitor.map((message) => message.id);

    if (!widgetUnreadInitialized) {
      widgetUnreadInitialized = true;
      nonVisitorIds.forEach((id) => seenNonVisitorMessageIds.add(id));
      widgetHostBridge.sendToHost('helmdesk:widget:unread', { count: 0 });
      return;
    }

    if (visible === true) {
      nonVisitorIds.forEach((id) => seenNonVisitorMessageIds.add(id));
      widgetHostBridge.sendToHost('helmdesk:widget:unread', { count: 0 });
      return;
    }

    const newlyArrived = nonVisitor.filter(
      (message) => !seenNonVisitorMessageIds.has(message.id),
    );
    if (newlyArrived.length === 0) {
      return;
    }

    const unreadCount = nonVisitor.length - seenNonVisitorMessageIds.size;
    widgetHostBridge.sendToHost('helmdesk:widget:unread', {
      count: Math.max(unreadCount, newlyArrived.length),
    });

    const latest = newlyArrived[newlyArrived.length - 1];
    widgetHostBridge.sendToHost('helmdesk:widget:toast', {
      text: latest.content ?? '',
      kind: latest.kind,
      sender_name: latest.sender_name,
      message_id: latest.id,
    });
  },
);

watch(
  () => widgetHostBridge?.shutdownRequested.value ?? false,
  (requested) => {
    if (requested) {
      closeConversationEventSource();
      clearPendingUploads();
    }
  },
);

onMounted(() => {
  if (props.interactive) {
    void loadState();
  }

  // 监听 <html> class 变化：系统主题实时切换时 initializeTheme 会增删 .dark，
  // 观察器据此更新 isDark，让渐变背景与气泡色跟随。
  syncIsDark();
  themeClassObserver = new MutationObserver(syncIsDark);
  themeClassObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
  });
});

onUnmounted(() => {
  closeConversationEventSource();
  clearPendingUploads();
  clearDemoMessages();
  themeClassObserver?.disconnect();
  themeClassObserver = null;
});
</script>

<template>
  <div
    class="flex h-full min-h-0 w-full flex-col overflow-hidden text-foreground"
    :style="pageStyle"
  >
    <ChannelPausedNotice v-if="pausedWithoutSession" :channel="props.channel" />
    <template v-else>
      <!-- 首页态：品牌欢迎屏、续聊卡片和进入聊天按钮。 -->
      <div
        v-if="activeView === 'home'"
        class="flex min-h-0 flex-1 flex-col overflow-y-auto px-6 py-8 sm:px-8"
      >
        <div v-if="showWidgetCloseButton" class="mb-2 flex justify-end">
          <button
            type="button"
            :aria-label="t('关闭聊天')"
            :title="t('关闭聊天')"
            class="inline-flex size-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-white/60 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none dark:hover:bg-white/10"
            @click="requestWidgetClose"
          >
            <X class="size-4" />
          </button>
        </div>

        <div class="flex items-center gap-3">
          <Avatar class="size-9">
            <AvatarImage
              v-if="props.channel.icon_url"
              :src="props.channel.icon_url"
              :alt="props.channel.site_name"
            />
            <AvatarFallback
              class="bg-[var(--standalone-primary)] text-sm font-semibold text-[var(--standalone-visitor-text)]"
            >
              {{ props.channel.site_name.slice(0, 1).toUpperCase() }}
            </AvatarFallback>
          </Avatar>
          <span class="truncate text-lg font-semibold text-foreground">
            {{ props.channel.site_name }}
          </span>
        </div>

        <h2
          v-if="homeWelcomeMessage"
          class="mt-8 text-3xl leading-snug font-bold whitespace-pre-line text-foreground"
        >
          {{ homeWelcomeMessage }}
        </h2>

        <div
          class="mt-8 rounded-2xl bg-white/90 p-4 shadow-[0_18px_40px_-20px_rgba(15,23,42,0.35)] backdrop-blur dark:bg-white/10"
        >
          <div class="flex items-center gap-3">
            <Avatar class="size-10">
              <AvatarImage
                v-if="props.channel.assistant_avatar_url"
                :src="props.channel.assistant_avatar_url"
                :alt="props.channel.assistant_name"
              />
              <AvatarImage
                v-else-if="props.channel.icon_url"
                :src="props.channel.icon_url"
                :alt="props.channel.site_name"
              />
              <AvatarFallback
                class="bg-[var(--standalone-primary)]/10 text-sm font-semibold text-[var(--standalone-primary)]"
              >
                {{ avatarFallback }}
              </AvatarFallback>
            </Avatar>
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-medium text-foreground">
                {{ props.channel.site_name }}
              </div>
              <div
                v-if="homeCardHint"
                class="truncate text-xs text-muted-foreground"
              >
                {{ homeCardHint }}
              </div>
            </div>
          </div>
          <button
            type="button"
            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-[var(--standalone-primary)] px-4 py-3 text-sm font-medium text-[var(--standalone-visitor-text)] transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
            @click="enterChat"
          >
            <MessageCircle class="size-4" />
            {{ t('进入聊天') }}
          </button>
        </div>

        <div class="mt-auto pt-8 text-center text-xs text-muted-foreground/70">
          {{ t('由 HelmDesk 提供技术支持') }}
        </div>
      </div>

      <!-- 聊天线程态 -->
      <template v-else>
        <header
          v-if="showThreadHeader"
          class="relative flex shrink-0 items-center justify-center px-4 py-3 sm:px-6"
        >
          <button
            v-if="props.channel.home_mode_enabled"
            type="button"
            :aria-label="t('返回首页')"
            :title="t('返回首页')"
            class="absolute left-4 inline-flex size-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-white/60 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none sm:left-6 dark:hover:bg-white/10"
            @click="backToHome"
          >
            <ArrowLeft class="size-5" />
          </button>

          <div
            class="flex max-w-[70%] items-center gap-2.5 rounded-full bg-white/80 px-4 py-2 shadow-[0_10px_30px_-16px_rgba(15,23,42,0.4)] backdrop-blur dark:bg-white/10"
          >
            <Avatar class="size-8 shrink-0">
              <AvatarImage
                v-if="props.channel.icon_url"
                :src="props.channel.icon_url"
                :alt="props.channel.site_name"
              />
              <AvatarFallback
                class="bg-[var(--standalone-primary)]/10 text-xs font-semibold text-[var(--standalone-primary)]"
              >
                {{ props.channel.site_name.slice(0, 1).toUpperCase() }}
              </AvatarFallback>
            </Avatar>
            <div class="min-w-0 text-left">
              <div class="truncate text-sm font-semibold text-foreground">
                {{ props.channel.site_name }}
              </div>
              <p
                v-if="props.channel.subtitle"
                class="truncate text-xs text-muted-foreground"
              >
                {{ props.channel.subtitle }}
              </p>
            </div>
          </div>

          <button
            v-if="showWidgetCloseButton"
            type="button"
            :aria-label="t('关闭聊天')"
            :title="t('关闭聊天')"
            class="absolute right-4 inline-flex size-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-white/60 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none sm:right-6 dark:hover:bg-white/10"
            @click="requestWidgetClose"
          >
            <X class="size-4" />
          </button>
        </header>

        <main class="flex min-h-0 flex-1 justify-center overflow-hidden">
          <div class="flex w-full flex-1 flex-col px-4 sm:px-6">
            <div
              ref="messageListEl"
              class="flex-1 [scrollbar-gutter:stable] space-y-4 overflow-y-auto py-6 pr-3"
            >
              <div
                v-if="showInlineGreeting && hasGreetingContent"
                class="flex items-start gap-3"
              >
                <Avatar class="mt-0.5 size-9 shrink-0">
                  <AvatarImage
                    v-if="props.channel.assistant_avatar_url"
                    :src="props.channel.assistant_avatar_url"
                    :alt="props.channel.assistant_name"
                  />
                  <AvatarFallback
                    class="bg-primary/10 text-[11px] font-semibold text-primary"
                  >
                    {{ avatarFallback }}
                  </AvatarFallback>
                </Avatar>
                <div class="min-w-0 flex-1 space-y-1">
                  <div class="text-xs text-muted-foreground">
                    {{ props.channel.assistant_name }}
                  </div>
                  <div
                    class="rounded-2xl rounded-tl-sm bg-[var(--standalone-assistant-bubble)] px-4 py-3 text-sm leading-relaxed text-[var(--standalone-assistant-text)]"
                  >
                    <p
                      v-if="effectiveGreetingMessage"
                      class="whitespace-pre-line opacity-80"
                    >
                      {{ effectiveGreetingMessage }}
                    </p>
                  </div>
                </div>
              </div>

              <template v-for="message in messages" :key="message.id">
                <div
                  v-if="isVisitorMessage(message.role)"
                  class="flex w-full flex-col gap-1"
                >
                  <div class="mr-12 text-right text-xs text-muted-foreground">
                    {{ formatDateTime(message.created_at) }}
                  </div>
                  <div class="flex w-full items-start justify-end gap-3">
                    <div
                      class="flex max-w-[80%] min-w-0 flex-col items-end gap-1"
                    >
                      <StandaloneMessageBubble
                        :message="message"
                        :interactive="props.interactive"
                        side="visitor"
                        :can-copy="canCopyMessage(message)"
                        :can-recall="
                          isMessageRecallable(message, contextMenuOpenedAt)
                        "
                        @menu-open-change="handleContextMenuOpenChange"
                        @copy="copyMessageContent(message)"
                        @quote="quoteMessage(message)"
                        @recall="recallMessage(message.id)"
                        @reedit="reeditRecalledMessage(message)"
                        @open-quoted="openQuotedTarget(message.quoted_message!)"
                      />
                    </div>
                    <Avatar class="size-9 shrink-0">
                      <AvatarImage
                        v-if="message.sender_avatar_url"
                        :src="message.sender_avatar_url"
                        :alt="senderLabel(message)"
                      />
                      <AvatarFallback
                        class="bg-muted text-[11px] font-semibold text-muted-foreground"
                      >
                        <template v-if="message.sender_name">{{
                          message.sender_name.slice(0, 1)
                        }}</template>
                        <User v-else class="size-4" />
                      </AvatarFallback>
                    </Avatar>
                  </div>
                </div>
                <div v-else class="flex w-full flex-col gap-1">
                  <div class="ml-12 text-xs text-muted-foreground">
                    {{ formatDateTime(message.created_at) }}
                  </div>
                  <div class="flex w-full items-start gap-3">
                    <Avatar class="size-9 shrink-0">
                      <AvatarImage
                        v-if="message.sender_avatar_url"
                        :src="message.sender_avatar_url"
                        :alt="senderLabel(message)"
                      />
                      <AvatarFallback
                        class="bg-primary/10 text-[11px] font-semibold text-primary"
                      >
                        {{ avatarFallback }}
                      </AvatarFallback>
                    </Avatar>
                    <div
                      class="flex max-w-[80%] min-w-0 flex-col items-start gap-1"
                    >
                      <StandaloneMessageBubble
                        :message="message"
                        :interactive="props.interactive"
                        side="assistant"
                        :can-copy="canCopyMessage(message)"
                        :can-recall="false"
                        @menu-open-change="handleContextMenuOpenChange"
                        @copy="copyMessageContent(message)"
                        @quote="quoteMessage(message)"
                        @open-quoted="openQuotedTarget(message.quoted_message!)"
                      />
                    </div>
                  </div>
                </div>
              </template>

              <div
                v-if="agentActivityVisible"
                class="flex w-full items-start gap-3"
                aria-live="polite"
              >
                <Avatar class="size-9 shrink-0">
                  <AvatarFallback
                    class="bg-primary/10 text-[11px] font-semibold text-primary"
                  >
                    {{ avatarFallback }}
                  </AvatarFallback>
                </Avatar>
                <div
                  class="flex items-center gap-1.5 rounded-2xl rounded-tl-sm bg-[var(--standalone-assistant-bubble)] px-4 py-3"
                  :aria-label="t('客服正在输入')"
                >
                  <span class="sr-only">{{ t('客服正在输入') }}</span>
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

              <div
                v-if="props.interactive && loading && messages.length === 0"
                class="text-center text-xs text-muted-foreground"
              >
                {{ t('正在加载会话……') }}
              </div>
            </div>

            <div
              v-if="showRatingPrompt || submittedRating"
              class="shrink-0 rounded-lg border bg-background/80 px-4 py-3"
            >
              <div
                v-if="submittedRating"
                class="flex items-center justify-center gap-2 text-sm text-muted-foreground"
              >
                <component
                  :is="
                    submittedRating.score === 'positive' ? ThumbsUp : ThumbsDown
                  "
                  class="size-4"
                />
                {{ t('感谢您的评价') }}
              </div>
              <template v-else>
                <p class="mb-2 text-center text-sm font-medium">
                  {{ t('本次服务您还满意吗？') }}
                </p>
                <div class="mb-2 flex justify-center gap-3">
                  <button
                    type="button"
                    :disabled="ratingSubmitting"
                    :class="[
                      'flex items-center gap-1.5 rounded-full border px-4 py-1.5 text-sm transition-colors disabled:opacity-50',
                      ratingScore === 'positive'
                        ? 'border-foreground bg-foreground text-background'
                        : 'hover:bg-muted',
                    ]"
                    @click="ratingScore = 'positive'"
                  >
                    <ThumbsUp class="size-4" />{{ t('满意') }}
                  </button>
                  <button
                    type="button"
                    :disabled="ratingSubmitting"
                    :class="[
                      'flex items-center gap-1.5 rounded-full border px-4 py-1.5 text-sm transition-colors disabled:opacity-50',
                      ratingScore === 'negative'
                        ? 'border-foreground bg-foreground text-background'
                        : 'hover:bg-muted',
                    ]"
                    @click="ratingScore = 'negative'"
                  >
                    <ThumbsDown class="size-4" />{{ t('不满意') }}
                  </button>
                </div>
                <textarea
                  v-if="ratingScore !== null"
                  v-model="ratingComment"
                  :placeholder="t('补充说明（选填）')"
                  rows="2"
                  maxlength="2000"
                  class="mb-2 w-full resize-none rounded-md border bg-background px-3 py-2 text-sm focus:outline-none"
                ></textarea>
                <div v-if="ratingScore !== null" class="flex justify-center">
                  <button
                    type="button"
                    :disabled="ratingSubmitting"
                    class="rounded-full bg-[var(--standalone-primary)] px-5 py-1.5 text-sm font-medium text-white disabled:opacity-50"
                    @click="submitRating"
                  >
                    {{ t('提交评价') }}
                  </button>
                </div>
              </template>
            </div>

            <div
              v-if="errorMessage"
              class="shrink-0 rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive"
            >
              {{ errorMessage }}
            </div>

            <div class="shrink-0">
              <div
                v-if="enabledSuggestionItems.length > 0"
                class="mb-3 flex flex-wrap justify-center gap-2"
              >
                <button
                  v-for="item in enabledSuggestionItems"
                  :key="item"
                  type="button"
                  :disabled="isComposerActionDisabled"
                  class="rounded-full border border-[var(--standalone-primary)]/25 bg-background/80 px-3 py-1.5 text-xs font-medium text-[var(--standalone-primary)] shadow-xs transition-colors hover:bg-background disabled:cursor-default disabled:opacity-50"
                  @click="sendSuggestedQuestion(item)"
                >
                  {{ item }}
                </button>
              </div>
              <div
                v-if="visiblePendingUploads.length > 0"
                class="mb-2 flex flex-wrap gap-2"
              >
                <template
                  v-for="pendingUpload in visiblePendingUploads"
                  :key="pendingUpload.id"
                >
                  <div
                    v-for="attachment in pendingUpload.attachments"
                    :key="attachment.id"
                    class="relative"
                  >
                    <img
                      v-if="
                        pendingUpload.kind === 'image' && attachment.previewUrl
                      "
                      :src="attachment.previewUrl"
                      :alt="attachment.name"
                      class="h-16 w-16 rounded-lg object-cover"
                    />
                    <div
                      v-else-if="pendingUpload.kind === 'image'"
                      class="flex h-16 w-16 items-center justify-center rounded-lg border bg-muted/40 text-muted-foreground"
                    >
                      <ImageIcon class="size-4" />
                    </div>
                    <div
                      v-else
                      class="flex h-16 max-w-40 items-center gap-2 rounded-lg border bg-background/60 px-2"
                    >
                      <Paperclip
                        class="size-4 shrink-0 text-muted-foreground"
                      />
                      <div class="min-w-0 text-xs">
                        <div class="truncate font-medium">
                          {{ attachment.name }}
                        </div>
                        <div class="text-muted-foreground">
                          {{ formatFileSize(attachment.byteSize) }}
                        </div>
                      </div>
                    </div>
                    <button
                      v-if="
                        attachment.status === 'failed' &&
                        canRetryPendingUpload(pendingUpload)
                      "
                      type="button"
                      class="absolute inset-0 flex items-center justify-center rounded-lg bg-black/40 text-[11px] font-medium text-white"
                      @click="retryPendingUpload(pendingUpload)"
                    >
                      {{ t('重试') }}
                    </button>
                    <div
                      v-else-if="attachment.status !== 'uploaded'"
                      class="absolute inset-0 flex items-center justify-center rounded-lg bg-black/40 text-[11px] font-medium text-white"
                    >
                      {{ pendingAttachmentStatusLabel(attachment) }}
                    </div>
                    <button
                      v-if="attachment.status === 'failed'"
                      type="button"
                      :title="t('移除')"
                      :aria-label="t('移除')"
                      class="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-destructive text-white shadow-sm"
                      @click="removePendingUpload(pendingUpload.id)"
                    >
                      <X class="size-3" />
                    </button>
                  </div>
                </template>
              </div>
              <StandaloneComposer
                ref="composerRef"
                v-model="composerValue"
                :can-interact="canInteract"
                :action-disabled="isComposerActionDisabled"
                :max-content-length="MAX_CONTENT_LENGTH"
                :quote="composerQuote"
                @send="sendMessage"
                @paste="handleComposerPaste"
                @file-select="handleComposerFileSelect"
                @input="handleComposerInput"
                @open-quote="openQuotedTarget(composerQuote!)"
                @clear-quote="clearComposerQuote"
              />
            </div>
          </div>
        </main>

        <ImagePreviewDialog
          v-if="quotedPreviewImages.length"
          v-model:open="quotedPreviewOpen"
          :images="quotedPreviewImages"
          :initial-id="quotedPreviewInitialId"
        />
        <Dialog v-model:open="quotedTextDialogOpen">
          <DialogContent
            class="max-h-[80vh] overflow-x-hidden overflow-y-auto sm:max-w-md"
          >
            <DialogHeader>
              <!-- 用户要求弹窗内只显示消息内容，不显示发件人。标题用 sr-only 保留可访问性。 -->
              <DialogTitle class="sr-only">{{ t('引用消息内容') }}</DialogTitle>
            </DialogHeader>
            <div
              class="min-w-0 text-sm leading-6 [overflow-wrap:anywhere] whitespace-pre-wrap"
            >
              {{ quotedTextDialogContent }}
            </div>
          </DialogContent>
        </Dialog>
      </template>
    </template>
  </div>
</template>
