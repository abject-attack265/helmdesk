/**
 * 管理收件箱 AI 回复助手的实例级选项偏好、候选请求、缓存与应用流程。
 */
import { useI18n } from '@/composables/useI18n';
import {
  readLocalStorageItem,
  removeLocalStorageItem,
  writeLocalStorageItem,
  type BrowserStorageContext,
} from '@/lib/browserStorage';
import inboxActions from '@/routes/app/inbox';
import type {
  EnumOptionData,
  FormPolishInboxReplyData,
  InboxReplyPolishCandidateData,
  InboxReplyPolishResultData,
  InboxSelectionData,
  ReplyAssistantMode,
  ReplyPolishTone,
} from '@/types/generated';
import axios from 'axios';
import {
  computed,
  nextTick,
  onUnmounted,
  ref,
  watch,
  type ComputedRef,
  type Ref,
} from 'vue';

const REPLY_POLISH_DEBOUNCE_MS = 600;
const REPLY_POLISH_CACHE_LIMIT = 20;
const REPLY_POLISH_TONE_STORAGE_KEY = 'helmdesk.inbox.reply-polish-tone:system';
const REPLY_ASSISTANT_MODE_VALUES: Record<ReplyAssistantMode, true> = {
  reply: true,
  rewrite: true,
};
const REPLY_POLISH_TONE_VALUES: Record<ReplyPolishTone, true> = {
  keep: true,
  professional: true,
  friendly: true,
  concise: true,
};

type InboxReplyPolishEnumOption<T extends string> = Omit<
  EnumOptionData,
  'value'
> & {
  value: T;
};

function isEnumValue<T extends string>(
  values: Record<T, true>,
  value: unknown,
): value is T {
  return typeof value === 'string' && Object.hasOwn(values, value);
}

function narrowEnumOptions<T extends string>(
  options: EnumOptionData[],
  values: Record<T, true>,
  optionName: string,
): InboxReplyPolishEnumOption<T>[] {
  return options.map((option) => {
    if (!isEnumValue(values, option.value)) {
      throw new Error(
        `收件箱 AI 回复助手收到无效的${optionName}选项：${String(option.value)}`,
      );
    }

    return {
      ...option,
      value: option.value,
    };
  });
}

function firstOptionValue<T extends string>(
  options: InboxReplyPolishEnumOption<T>[],
  optionName: string,
): T {
  const option = options[0];
  if (!option) {
    throw new Error(`收件箱 AI 回复助手缺少${optionName}选项`);
  }

  return option.value;
}

interface UseInboxReplyPolishOptions {
  selection: ComputedRef<InboxSelectionData | null>;
  replyContent: Ref<string>;
  quotedMessageId: ComputedRef<string | null>;
  modeOptions: ComputedRef<EnumOptionData[]>;
  toneOptions: ComputedRef<EnumOptionData[]>;
  replyActionDisabled: ComputedRef<boolean>;
}

interface ResetInboxReplyPolishOptions {
  clearCache?: boolean;
}

interface UseInboxReplyPolishReturn {
  open: Ref<boolean>;
  selectedMode: Ref<ReplyAssistantMode>;
  selectedTone: Ref<ReplyPolishTone>;
  validatedModeOptions: ComputedRef<
    InboxReplyPolishEnumOption<ReplyAssistantMode>[]
  >;
  validatedToneOptions: ComputedRef<
    InboxReplyPolishEnumOption<ReplyPolishTone>[]
  >;
  candidates: Ref<InboxReplyPolishCandidateData[]>;
  loading: Ref<boolean>;
  error: Ref<string | null>;
  canUse: ComputedRef<boolean>;
  buttonTitle: ComputedRef<string>;
  refreshCandidates: () => void;
  applyCandidate: (content: string) => Promise<void>;
}

/** 创建收件箱 AI 回复助手的响应式状态和请求控制器。 */
export function useInboxReplyPolish(
  options: UseInboxReplyPolishOptions,
): UseInboxReplyPolishReturn {
  const {
    selection,
    replyContent,
    quotedMessageId,
    modeOptions,
    toneOptions,
    replyActionDisabled,
  } = options;
  const { t } = useI18n();

  const validatedModeOptions = computed(() =>
    narrowEnumOptions(
      modeOptions.value,
      REPLY_ASSISTANT_MODE_VALUES,
      '助手模式',
    ),
  );
  const validatedToneOptions = computed(() =>
    narrowEnumOptions(toneOptions.value, REPLY_POLISH_TONE_VALUES, '语气'),
  );
  const open = ref(false);
  const selectedMode = ref<ReplyAssistantMode>(
    firstOptionValue(validatedModeOptions.value, '助手模式'),
  );
  const selectedTone = ref<ReplyPolishTone>(
    firstOptionValue(validatedToneOptions.value, '语气'),
  );
  const candidates = ref<InboxReplyPolishCandidateData[]>([]);
  const signature = ref('');
  const loading = ref(false);
  const error = ref<string | null>(null);
  const candidateCache = new Map<string, InboxReplyPolishCandidateData[]>();

  let requestSequence = 0;
  let requestTimer: number | null = null;
  let requestController: AbortController | null = null;

  const hasSelectedMode = computed(() =>
    hasOptionValue(validatedModeOptions.value, selectedMode.value),
  );
  const hasSelectedTone = computed(() =>
    hasOptionValue(validatedToneOptions.value, selectedTone.value),
  );
  const canUse = computed(
    () =>
      !replyActionDisabled.value &&
      hasSelectedMode.value &&
      hasSelectedTone.value,
  );
  const buttonTitle = computed(() => {
    if (!selection.value?.can_reply) {
      return t('当前会话不可回复');
    }
    if (!hasSelectedMode.value) {
      return t('请选择回复方式');
    }
    if (!hasSelectedTone.value) {
      return t('请选择语气');
    }

    return t('帮我写回复');
  });

  function hasOptionValue<T extends string>(
    availableOptions: InboxReplyPolishEnumOption<T>[],
    value: T,
  ): boolean {
    return availableOptions.some((option) => option.value === value);
  }

  function toneStorageContext(): BrowserStorageContext {
    return {
      channel: '[inbox-reply-polish]',
      details: { scope: 'system', preference: 'tone' },
    };
  }

  function clearStoredTone(): void {
    removeLocalStorageItem(REPLY_POLISH_TONE_STORAGE_KEY, toneStorageContext());
  }

  function loadStoredTone(): ReplyPolishTone | null {
    const value = readLocalStorageItem(
      REPLY_POLISH_TONE_STORAGE_KEY,
      toneStorageContext(),
    );
    if (value === null) {
      return null;
    }
    if (!isEnumValue(REPLY_POLISH_TONE_VALUES, value)) {
      console.warn('[inbox-reply-polish] 忽略无效的本地语气偏好', {
        scope: 'system',
      });
      clearStoredTone();

      return null;
    }

    return value;
  }

  function storeToneSelection(tone: ReplyPolishTone): void {
    writeLocalStorageItem(
      REPLY_POLISH_TONE_STORAGE_KEY,
      tone,
      toneStorageContext(),
    );
  }

  function selectDefaultTone(): void {
    selectedTone.value = firstOptionValue(validatedToneOptions.value, '语气');
  }

  /** 保留有效语气选择，并按当前实例恢复本地偏好。 */
  function syncToneSelection(restoreStoredPreference = false): void {
    if (
      !restoreStoredPreference &&
      hasOptionValue(validatedToneOptions.value, selectedTone.value)
    ) {
      return;
    }

    const remembered = loadStoredTone();
    if (
      remembered !== null &&
      hasOptionValue(validatedToneOptions.value, remembered)
    ) {
      selectedTone.value = remembered;

      return;
    }

    if (remembered !== null) {
      console.warn('[inbox-reply-polish] 忽略不可用的本地语气偏好', {
        scope: 'system',
      });
      clearStoredTone();
    }

    selectDefaultTone();
  }

  function selectDefaultMode(): void {
    selectedMode.value = firstOptionValue(
      validatedModeOptions.value,
      '助手模式',
    );
  }

  function syncModeSelection(): void {
    if (hasOptionValue(validatedModeOptions.value, selectedMode.value)) {
      return;
    }

    selectDefaultMode();
  }

  function cloneCandidates(
    values: InboxReplyPolishCandidateData[],
  ): InboxReplyPolishCandidateData[] {
    return values.map((candidate) => ({ ...candidate }));
  }

  function clearCandidateCache(): void {
    candidateCache.clear();
  }

  /** 按最近使用顺序保存候选项，并限制缓存条目数量。 */
  function rememberCandidates(
    cacheSignature: string,
    values: InboxReplyPolishCandidateData[],
  ): void {
    candidateCache.delete(cacheSignature);
    candidateCache.set(cacheSignature, cloneCandidates(values));

    while (candidateCache.size > REPLY_POLISH_CACHE_LIMIT) {
      const expiredKey = candidateCache.keys().next().value as string;
      candidateCache.delete(expiredKey);
    }
  }

  function restoreCandidates(
    cacheSignature: string,
  ): InboxReplyPolishCandidateData[] | null {
    const cached = candidateCache.get(cacheSignature);
    if (!cached) {
      return null;
    }

    candidateCache.delete(cacheSignature);
    candidateCache.set(cacheSignature, cached);

    return cloneCandidates(cached);
  }

  function cancelRequest(): void {
    if (requestTimer !== null) {
      clearTimeout(requestTimer);
      requestTimer = null;
    }

    requestController?.abort();
    requestController = null;
    requestSequence += 1;
    loading.value = false;
  }

  function resetPreview(resetOptions: ResetInboxReplyPolishOptions = {}): void {
    cancelRequest();
    candidates.value = [];
    signature.value = '';
    loading.value = false;
    error.value = null;

    if (resetOptions.clearCache === true) {
      clearCandidateCache();
    }
  }

  function resolveRequestError(requestError: unknown): string {
    if (axios.isAxiosError(requestError)) {
      const data = requestError.response?.data as
        { message?: string; errors?: Record<string, string[]> } | undefined;
      const status = requestError.response?.status;
      if (status !== undefined && status >= 400 && status < 500) {
        if (data?.errors?.content?.[0]) {
          return data.errors.content[0];
        }
        if (data?.errors?.tone?.[0]) {
          return data.errors.tone[0];
        }
        if (typeof data?.message === 'string' && data.message) {
          return data.message;
        }
      }
    }

    return t('生成回复失败，请稍后重试');
  }

  function buildSignature(
    conversationId: string,
    mode: ReplyAssistantMode,
    source: string,
    tone: ReplyPolishTone,
    quoteId: string | null,
  ): string {
    return JSON.stringify([conversationId, mode, source, tone, quoteId]);
  }

  function parseResult(payload: unknown): InboxReplyPolishResultData {
    if (
      typeof payload !== 'object' ||
      payload === null ||
      Array.isArray(payload) ||
      !('candidates' in payload) ||
      !Array.isArray(payload.candidates) ||
      payload.candidates.some(
        (candidate) =>
          typeof candidate !== 'object' ||
          candidate === null ||
          Array.isArray(candidate) ||
          !('id' in candidate) ||
          typeof candidate.id !== 'string' ||
          !('content' in candidate) ||
          typeof candidate.content !== 'string',
      )
    ) {
      throw new Error('收件箱 AI 回复候选响应格式无效');
    }

    return payload as InboxReplyPolishResultData;
  }

  function requestContextMatches(
    requestId: number,
    conversationId: string,
    mode: ReplyAssistantMode,
    source: string,
    tone: ReplyPolishTone,
    quoteId: string | null,
    requestSignature: string,
  ): boolean {
    return (
      requestId === requestSequence &&
      selection.value?.conversation.id === conversationId &&
      replyContent.value.trim() === source &&
      selectedMode.value === mode &&
      selectedTone.value === tone &&
      quotedMessageId.value === quoteId &&
      signature.value === requestSignature &&
      !replyActionDisabled.value
    );
  }

  /** 请求回复候选，并仅接纳仍匹配当前编辑上下文的响应。 */
  async function requestCandidates(
    conversationId: string,
    mode: ReplyAssistantMode,
    source: string,
    tone: ReplyPolishTone,
    quoteId: string | null,
    requestSignature: string,
  ): Promise<void> {
    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;
    const requestId = ++requestSequence;
    error.value = null;
    loading.value = true;

    try {
      const payload: FormPolishInboxReplyData = {
        mode,
        content: source,
        tone,
        quoted_message_id: quoteId,
      };
      const response = await axios.post<InboxReplyPolishResultData>(
        inboxActions.conversations.reply.polish.url({
          conversation: conversationId,
        }),
        payload,
        { signal: controller.signal },
      );

      if (
        controller.signal.aborted ||
        !requestContextMatches(
          requestId,
          conversationId,
          mode,
          source,
          tone,
          quoteId,
          requestSignature,
        )
      ) {
        return;
      }

      const responseCandidates = parseResult(response.data).candidates;
      const nextCandidates = responseCandidates.filter(
        (candidate) => candidate.content.trim() !== '',
      );
      candidates.value = nextCandidates;
      if (nextCandidates.length > 0) {
        rememberCandidates(requestSignature, nextCandidates);
      } else {
        console.warn('[inbox-reply-polish] 接口未返回可用候选', {
          scope: 'system',
          conversationId,
          mode,
          tone,
          candidateCount: responseCandidates.length,
        });
        error.value = t('生成回复失败，请稍后重试');
      }
    } catch (requestError) {
      if (
        axios.isCancel(requestError) ||
        controller.signal.aborted ||
        !requestContextMatches(
          requestId,
          conversationId,
          mode,
          source,
          tone,
          quoteId,
          requestSignature,
        )
      ) {
        return;
      }

      console.warn('[inbox-reply-polish] 回复候选请求失败', {
        scope: 'system',
        conversationId,
        mode,
        tone,
        errorType: axios.isAxiosError(requestError)
          ? 'AxiosError'
          : requestError instanceof Error
            ? requestError.name
            : typeof requestError,
        errorCode: axios.isAxiosError(requestError)
          ? requestError.code
          : undefined,
        status: axios.isAxiosError(requestError)
          ? requestError.response?.status
          : undefined,
      });
      candidates.value = [];
      error.value = resolveRequestError(requestError);
    } finally {
      if (requestController === controller) {
        requestController = null;
      }
      if (requestId === requestSequence) {
        loading.value = false;
      }
    }
  }

  /** 根据当前编辑上下文复用缓存或防抖请求回复候选。 */
  function schedule(force = false): void {
    if (requestTimer !== null) {
      clearTimeout(requestTimer);
      requestTimer = null;
    }

    const conversation = selection.value?.conversation;
    const mode = selectedMode.value;
    const source = replyContent.value.trim();
    const tone = selectedTone.value;
    const quoteId = quotedMessageId.value;

    if (
      !open.value ||
      !conversation ||
      replyActionDisabled.value ||
      !hasSelectedMode.value ||
      !hasSelectedTone.value
    ) {
      if (open.value) {
        resetPreview();
      } else {
        cancelRequest();
      }

      return;
    }

    if (mode === 'rewrite' && source === '') {
      resetPreview();
      error.value = t('请先输入要改写的内容');

      return;
    }

    const nextSignature = buildSignature(
      conversation.id,
      mode,
      source,
      tone,
      quoteId,
    );
    if (
      !force &&
      nextSignature === signature.value &&
      candidates.value.length > 0 &&
      error.value === null
    ) {
      return;
    }

    if (!force) {
      const cachedCandidates = restoreCandidates(nextSignature);
      if (cachedCandidates) {
        cancelRequest();
        signature.value = nextSignature;
        candidates.value = cachedCandidates;
        error.value = null;

        return;
      }
    }

    cancelRequest();
    signature.value = nextSignature;
    candidates.value = [];
    error.value = null;

    if (typeof window === 'undefined') {
      return;
    }

    loading.value = true;
    requestTimer = window.setTimeout(() => {
      requestTimer = null;
      void requestCandidates(
        conversation.id,
        mode,
        source,
        tone,
        quoteId,
        nextSignature,
      );
    }, REPLY_POLISH_DEBOUNCE_MS);
  }

  function refreshCandidates(): void {
    schedule(true);
  }

  async function applyCandidate(content: string): Promise<void> {
    const candidate = content.trim();
    if (candidate === '' || replyActionDisabled.value) {
      return;
    }

    open.value = false;
    replyContent.value = candidate;
    resetPreview({ clearCache: true });
    await nextTick();
  }

  syncToneSelection(true);
  watch(toneOptions, () => syncToneSelection());
  watch(modeOptions, syncModeSelection, { immediate: true });
  watch(selectedMode, () => schedule());
  watch(selectedTone, (value) => {
    storeToneSelection(value);
    schedule();
  });
  watch(open, (isOpen) => {
    if (isOpen) {
      selectDefaultMode();
      error.value = null;
      schedule();

      return;
    }

    cancelRequest();
  });
  watch(
    () => selection.value?.conversation.id,
    () => {
      open.value = false;
      resetPreview({ clearCache: true });
    },
  );
  watch([replyContent, quotedMessageId], () => {
    if (open.value) {
      schedule();
    }
  });
  watch(replyActionDisabled, (disabled) => {
    if (!disabled) {
      return;
    }

    open.value = false;
    resetPreview();
  });

  onUnmounted(cancelRequest);

  return {
    open,
    selectedMode,
    selectedTone,
    validatedModeOptions,
    validatedToneOptions,
    candidates,
    loading,
    error,
    canUse,
    buttonTitle,
    refreshCandidates,
    applyCandidate,
  };
}
