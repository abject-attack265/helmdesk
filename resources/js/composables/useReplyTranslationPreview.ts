/**
 * 管理收件箱回复译文的请求、编辑状态和表单字段。
 */
import { useI18n } from '@/composables/useI18n';
import { hasTranslatableLetters } from '@/lib/translationText';
import inboxActions from '@/routes/app/inbox';
import type { InboxSelectionData } from '@/types/generated';
import axios from 'axios';
import {
  type ComputedRef,
  type Ref,
  computed,
  onUnmounted,
  ref,
  watch,
} from 'vue';

export interface UseReplyTranslationPreviewOptions {
  selection: ComputedRef<InboxSelectionData | null>;
  replyContent: Ref<string>;
  enabled: Ref<boolean>;
}

export interface ReplyTranslationFormFields {
  visitor_content: string | null;
  visitor_locale: string | null;
  source_locale: string | null;
}

export interface UseReplyTranslationPreviewReturn {
  draft: Ref<string>;
  loading: Ref<boolean>;
  touched: Ref<boolean>;
  error: Ref<string | null>;
  visitorLocale: Ref<string | null>;
  sourceLocale: Ref<string | null>;
  ready: ComputedRef<boolean>;
  requirementMessage: ComputedRef<string | null>;
  showPreview: ComputedRef<boolean>;
  title: ComputedRef<string>;
  active: ComputedRef<boolean>;
  expectedVisitorLocale: ComputedRef<string | null>;
  applyToForm: (form: ReplyTranslationFormFields) => void;
  clear: (form?: ReplyTranslationFormFields) => void;
  cleanup: () => void;
  schedule: () => void;
}

const REPLY_TRANSLATION_DEBOUNCE_MS = 600;

export function useReplyTranslationPreview(
  options: UseReplyTranslationPreviewOptions,
): UseReplyTranslationPreviewReturn {
  const { selection, replyContent, enabled } = options;

  const { t } = useI18n();

  const replyTranslationDraft = ref('');
  const replyTranslationSource = ref('');
  const replyVisitorLocale = ref<string | null>(null);
  const replySourceLocale = ref<string | null>(null);
  const replyTranslationLoading = ref(false);
  const replyTranslationTouched = ref(false);
  const replyTranslationError = ref<string | null>(null);
  let replyTranslationTimer: number | null = null;
  let replyTranslationController: AbortController | null = null;

  const replyExpectedVisitorLocale = computed(
    () => selection.value?.conversation.visitor_locale ?? null,
  );

  const replyHasTranslatableLetters = computed(() =>
    hasTranslatableLetters(replyContent.value),
  );

  const replyAutoTranslationActive = computed(
    () =>
      enabled.value &&
      Boolean(selection.value?.can_translate_messages) &&
      Boolean(selection.value?.can_reply) &&
      replyExpectedVisitorLocale.value !== null,
  );

  const replyTranslationReady = computed(() => {
    if (!replyAutoTranslationActive.value) {
      return true;
    }

    const content = replyContent.value.trim();
    if (content === '') {
      return false;
    }
    if (!replyHasTranslatableLetters.value) {
      return true;
    }
    return (
      !replyTranslationLoading.value &&
      replyTranslationError.value === null &&
      replyTranslationSource.value === content &&
      replyVisitorLocale.value === replyExpectedVisitorLocale.value &&
      replySourceLocale.value !== null &&
      replyTranslationDraft.value.trim().length > 0
    );
  });

  const replyTranslationRequirementMessage = computed(() => {
    if (!replyAutoTranslationActive.value || replyContent.value.trim() === '') {
      return null;
    }
    if (replyTranslationLoading.value) {
      return null;
    }
    if (replyTranslationError.value !== null) {
      return replyTranslationError.value;
    }
    if (!replyTranslationReady.value) {
      return t('请确认翻译内容后再发送');
    }

    return null;
  });

  const showReplyTranslationPreview = computed(
    () =>
      (replyAutoTranslationActive.value &&
        replyHasTranslatableLetters.value &&
        replyContent.value.trim().length > 0) ||
      replyTranslationLoading.value ||
      replyTranslationDraft.value.trim().length > 0 ||
      replyTranslationError.value !== null,
  );

  const replyTranslationTitle = computed(() => t('访客将看到'));

  function clearReplyTranslationPreview(
    form?: ReplyTranslationFormFields,
  ): void {
    if (replyTranslationTimer !== null) {
      window.clearTimeout(replyTranslationTimer);
      replyTranslationTimer = null;
    }
    replyTranslationController?.abort();
    replyTranslationController = null;
    replyTranslationDraft.value = '';
    replyTranslationSource.value = '';
    replyVisitorLocale.value = null;
    replySourceLocale.value = null;
    replyTranslationLoading.value = false;
    replyTranslationTouched.value = false;
    replyTranslationError.value = null;
    if (form) {
      form.visitor_content = null;
      form.visitor_locale = null;
      form.source_locale = null;
    }
  }

  function applyReplyTranslationToForm(form: ReplyTranslationFormFields): void {
    const text = replyTranslationDraft.value.trim();
    form.visitor_content =
      text !== '' && replyVisitorLocale.value !== null ? text : null;
    form.visitor_locale = text !== '' ? replyVisitorLocale.value : null;
    form.source_locale = text !== '' ? replySourceLocale.value : null;
  }

  function parseReplyTranslationResponse(payload: unknown): {
    visitor_content: string | null;
    visitor_locale: string | null;
    source_locale: string | null;
  } {
    if (
      typeof payload !== 'object' ||
      payload === null ||
      Array.isArray(payload)
    ) {
      throw new Error('回复翻译预览响应格式无效');
    }

    const data = payload as Record<string, unknown>;
    for (const field of [
      'visitor_content',
      'visitor_locale',
      'source_locale',
    ] as const) {
      if (data[field] !== null && typeof data[field] !== 'string') {
        throw new Error(`回复翻译预览响应的 ${field} 格式无效`);
      }
    }

    return data as {
      visitor_content: string | null;
      visitor_locale: string | null;
      source_locale: string | null;
    };
  }

  function resolveReplyTranslationError(error: unknown): string {
    if (axios.isAxiosError<{ message?: string }>(error)) {
      const status = error.response?.status;
      const message = error.response?.data?.message;
      if (
        status !== undefined &&
        status >= 400 &&
        status < 500 &&
        typeof message === 'string' &&
        message !== ''
      ) {
        return message;
      }
    }

    return t('翻译失败');
  }

  async function requestReplyTranslationPreview(
    conversationId: string,
    content: string,
  ): Promise<void> {
    replyTranslationController?.abort();
    const controller = new AbortController();
    replyTranslationController = controller;
    replyTranslationLoading.value = true;
    replyTranslationError.value = null;

    try {
      const response = await axios.post<unknown>(
        inboxActions.conversations.reply.translationPreview.url({
          conversation: conversationId,
        }),
        { content },
        { signal: controller.signal },
      );

      if (
        controller.signal.aborted ||
        selection.value?.conversation.id !== conversationId ||
        replyContent.value.trim() !== content
      ) {
        return;
      }

      const data = parseReplyTranslationResponse(response.data);
      replyTranslationSource.value = content;
      replyVisitorLocale.value = data.visitor_locale;
      replySourceLocale.value = data.source_locale;
      replyTranslationError.value =
        data.visitor_content === null ? t('翻译失败') : null;
      if (data.visitor_content === null) {
        console.warn('[inbox-reply-translation] 接口未返回回复译文', {
          scope: 'system',
          conversationId,
          visitorLocale: data.visitor_locale,
          sourceLocale: data.source_locale,
        });
      }
      if (!replyTranslationTouched.value) {
        replyTranslationDraft.value = data.visitor_content ?? '';
      }
    } catch (error) {
      if (controller.signal.aborted || axios.isCancel(error)) {
        return;
      }

      console.warn('[inbox-reply-translation] 回复翻译预览请求失败', {
        scope: 'system',
        conversationId,
        status: axios.isAxiosError(error) ? error.response?.status : undefined,
        code: axios.isAxiosError(error) ? error.code : undefined,
        errorType: axios.isAxiosError(error)
          ? 'AxiosError'
          : error instanceof Error
            ? error.name
            : typeof error,
      });
      replyTranslationError.value = resolveReplyTranslationError(error);
    } finally {
      if (replyTranslationController === controller) {
        replyTranslationController = null;
        replyTranslationLoading.value = false;
      }
    }
  }

  function scheduleReplyTranslationPreview(): void {
    if (replyTranslationTimer !== null) {
      window.clearTimeout(replyTranslationTimer);
      replyTranslationTimer = null;
    }

    const conversationId = selection.value?.conversation.id;
    const content = replyContent.value.trim();
    if (
      !conversationId ||
      content === '' ||
      !replyHasTranslatableLetters.value ||
      !replyAutoTranslationActive.value
    ) {
      clearReplyTranslationPreview();
      return;
    }

    if (content !== replyTranslationSource.value) {
      replyTranslationTouched.value = false;
      replyTranslationDraft.value = '';
      replyVisitorLocale.value = null;
      replySourceLocale.value = null;
    }

    replyTranslationTimer = window.setTimeout(() => {
      replyTranslationTimer = null;
      void requestReplyTranslationPreview(conversationId, content);
    }, REPLY_TRANSLATION_DEBOUNCE_MS);
  }

  watch(
    () => [
      replyContent.value,
      selection.value?.conversation.id,
      selection.value?.can_reply,
      selection.value?.can_translate_messages,
      selection.value?.conversation.visitor_locale,
      enabled.value,
    ],
    () => scheduleReplyTranslationPreview(),
  );

  function cleanup(): void {
    if (replyTranslationTimer !== null) {
      window.clearTimeout(replyTranslationTimer);
      replyTranslationTimer = null;
    }
    replyTranslationController?.abort();
    replyTranslationController = null;
  }

  onUnmounted(cleanup);

  return {
    draft: replyTranslationDraft,
    loading: replyTranslationLoading,
    touched: replyTranslationTouched,
    error: replyTranslationError,
    visitorLocale: replyVisitorLocale,
    sourceLocale: replySourceLocale,
    ready: replyTranslationReady,
    requirementMessage: replyTranslationRequirementMessage,
    showPreview: showReplyTranslationPreview,
    title: replyTranslationTitle,
    active: replyAutoTranslationActive,
    expectedVisitorLocale: replyExpectedVisitorLocale,
    applyToForm: applyReplyTranslationToForm,
    clear: clearReplyTranslationPreview,
    cleanup,
    schedule: scheduleReplyTranslationPreview,
  };
}
