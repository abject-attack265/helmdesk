/**
 * 管理收件箱时间线、消息翻译与回复翻译的实例级本地偏好。
 */
import { useI18n } from '@/composables/useI18n';
import {
  readLocalStorageItem,
  writeLocalStorageItem,
  type BrowserStorageContext,
} from '@/lib/browserStorage';
import type { EnumOptionData, InboxSelectionData } from '@/types/generated';
import {
  computed,
  onMounted,
  ref,
  watch,
  type ComputedRef,
  type Ref,
} from 'vue';

const SHOW_TIMELINE_EVENTS_STORAGE_KEY = 'helmdesk.inbox.show_timeline_events';
const AUTO_TRANSLATE_VISIBLE_STORAGE_KEY =
  'helmdesk.inbox.auto_translate_visible.system';
const AUTO_TRANSLATE_REPLY_STORAGE_KEY =
  'helmdesk.inbox.auto_translate_reply.system';
const TRANSLATION_SOURCE_LOCALE_STORAGE_KEY =
  'helmdesk.inbox.translation_source_locale.system';
const TRANSLATION_TARGET_LOCALE_STORAGE_KEY =
  'helmdesk.inbox.translation_target_locale.system';

interface UseInboxTranslationPreferencesOptions {
  currentUserLocale: ComputedRef<string>;
  receptionLanguageOptions: ComputedRef<EnumOptionData[]>;
  selection: ComputedRef<InboxSelectionData | null>;
}

interface UseInboxTranslationPreferencesReturn {
  showTimelineEvents: Ref<boolean>;
  autoTranslateVisibleMessages: Ref<boolean>;
  autoTranslateReply: Ref<boolean>;
  translationSourceLocale: Ref<string>;
  translationTargetLocale: Ref<string>;
  translationPopoverOpen: Ref<boolean>;
  translationEnabled: ComputedRef<boolean>;
  translationConversationScopeId: ComputedRef<string | null>;
  translationSourceOptions: ComputedRef<
    Array<{ value: string; label: string }>
  >;
  replyAutoTranslateToggleTitle: ComputedRef<string>;
  toggleTimelineEvents: () => void;
  toggleReplyAutoTranslate: () => void;
  translateCurrentConversation: () => void;
}

interface ReceptionLanguageOption {
  value: string;
  label: string;
}

/** 创建收件箱翻译与时间线显示偏好。 */
export function useInboxTranslationPreferences(
  options: UseInboxTranslationPreferencesOptions,
): UseInboxTranslationPreferencesReturn {
  const { currentUserLocale, receptionLanguageOptions, selection } = options;
  const { t } = useI18n();
  const validatedReceptionLanguageOptions = computed<ReceptionLanguageOption[]>(
    () =>
      receptionLanguageOptions.value.map((option) => {
        if (typeof option.value !== 'string' || option.value.trim() === '') {
          throw new Error('收件箱翻译语言选项必须使用非空字符串值');
        }

        return {
          value: option.value,
          label: option.label,
        };
      }),
  );

  function storageContext(preference: string): BrowserStorageContext {
    return {
      channel: '[inbox-translation-preferences]',
      details: { scope: 'system', preference },
    };
  }

  function readLocalPreference(key: string, preference: string): string | null {
    return readLocalStorageItem(key, storageContext(preference));
  }

  function writeLocalPreference(
    key: string,
    value: string,
    preference: string,
  ): void {
    writeLocalStorageItem(key, value, storageContext(preference));
  }

  function readBooleanPreference(
    key: string,
    preference: string,
    fallback: boolean,
  ): boolean {
    const stored = readLocalPreference(key, preference);
    if (stored === null) {
      return fallback;
    }
    if (stored === 'true') {
      return true;
    }
    if (stored === 'false') {
      return false;
    }

    console.warn('[inbox-translation-preferences] 忽略无效的本地布尔偏好', {
      scope: 'system',
      preference,
      stored,
    });

    return fallback;
  }

  function getStoredShowTimelineEvents(): boolean {
    return readBooleanPreference(
      SHOW_TIMELINE_EVENTS_STORAGE_KEY,
      'showTimelineEvents',
      true,
    );
  }

  function getStoredAutoTranslateVisible(): boolean {
    return readBooleanPreference(
      AUTO_TRANSLATE_VISIBLE_STORAGE_KEY,
      'autoTranslateVisibleMessages',
      false,
    );
  }

  function getStoredAutoTranslateReply(): boolean {
    return readBooleanPreference(
      AUTO_TRANSLATE_REPLY_STORAGE_KEY,
      'autoTranslateReply',
      false,
    );
  }

  function getStoredTranslationSourceLocale(): string {
    const stored = readLocalPreference(
      TRANSLATION_SOURCE_LOCALE_STORAGE_KEY,
      'translationSourceLocale',
    );
    if (stored === null) {
      return 'auto';
    }

    const available = validatedReceptionLanguageOptions.value.some(
      (option) => option.value === stored,
    );
    if (stored === 'auto' || available) {
      return stored;
    }

    console.warn('[inbox-translation-preferences] 忽略无效的本地语言偏好', {
      scope: 'system',
      preference: 'translationSourceLocale',
      stored,
    });

    return 'auto';
  }

  /** 目标语言优先使用当前界面语言，否则使用第一个可选语言。 */
  function defaultTranslationTargetLocale(): string {
    const availableLocales = validatedReceptionLanguageOptions.value.map(
      (option) => option.value,
    );
    if (availableLocales.length === 0) {
      throw new Error('收件箱缺少可用翻译语言');
    }

    return availableLocales.includes(currentUserLocale.value)
      ? currentUserLocale.value
      : availableLocales[0];
  }

  function getStoredTranslationTargetLocale(): string {
    const availableLocales = validatedReceptionLanguageOptions.value.map(
      (option) => option.value,
    );
    const fallback = defaultTranslationTargetLocale();
    const stored = readLocalPreference(
      TRANSLATION_TARGET_LOCALE_STORAGE_KEY,
      'translationTargetLocale',
    );
    if (stored === null) {
      return fallback;
    }
    if (availableLocales.includes(stored)) {
      return stored;
    }

    console.warn('[inbox-translation-preferences] 忽略无效的本地语言偏好', {
      scope: 'system',
      preference: 'translationTargetLocale',
      stored,
    });

    return fallback;
  }

  const showTimelineEvents = ref(true);
  const autoTranslateVisibleMessages = ref(false);
  const autoTranslateReply = ref(false);
  const translationSourceLocale = ref('auto');
  const translationTargetLocale = ref(defaultTranslationTargetLocale());
  const manuallyTranslatedConversationId = ref<string | null>(null);
  const translationPopoverOpen = ref(false);

  function loadStoredPreferences(): void {
    showTimelineEvents.value = getStoredShowTimelineEvents();
    autoTranslateVisibleMessages.value = getStoredAutoTranslateVisible();
    autoTranslateReply.value = getStoredAutoTranslateReply();
    translationSourceLocale.value = getStoredTranslationSourceLocale();
    translationTargetLocale.value = getStoredTranslationTargetLocale();
  }

  const translationEnabled = computed(
    () =>
      autoTranslateVisibleMessages.value ||
      (manuallyTranslatedConversationId.value !== null &&
        selection.value?.conversation.id ===
          manuallyTranslatedConversationId.value),
  );
  const translationConversationScopeId = computed(() =>
    autoTranslateVisibleMessages.value
      ? null
      : manuallyTranslatedConversationId.value,
  );
  const translationSourceOptions = computed(() => [
    { value: 'auto', label: t('自动识别') },
    ...validatedReceptionLanguageOptions.value.map((option) => ({
      value: option.value,
      label: option.label,
    })),
  ]);
  const replyAutoTranslateToggleTitle = computed(() =>
    autoTranslateReply.value ? t('关闭自动翻译') : t('发送前自动翻译'),
  );

  function toggleTimelineEvents(): void {
    showTimelineEvents.value = !showTimelineEvents.value;
  }

  function toggleReplyAutoTranslate(): void {
    autoTranslateReply.value = !autoTranslateReply.value;
  }

  /** 切换当前会话翻译；全局模式下显示原文会同时关闭全局翻译。 */
  function translateCurrentConversation(): void {
    const currentSelection = selection.value;
    if (!currentSelection) {
      throw new Error('当前没有可翻译的会话');
    }
    if (!currentSelection.can_translate_messages) {
      throw new Error('当前会话不支持翻译');
    }

    if (autoTranslateVisibleMessages.value) {
      autoTranslateVisibleMessages.value = false;
      manuallyTranslatedConversationId.value = null;

      return;
    }

    if (
      manuallyTranslatedConversationId.value ===
      currentSelection.conversation.id
    ) {
      manuallyTranslatedConversationId.value = null;

      return;
    }

    manuallyTranslatedConversationId.value = currentSelection.conversation.id;
  }

  watch(showTimelineEvents, (value) => {
    writeLocalPreference(
      SHOW_TIMELINE_EVENTS_STORAGE_KEY,
      value ? 'true' : 'false',
      'showTimelineEvents',
    );
  });

  watch(autoTranslateVisibleMessages, (value) => {
    writeLocalPreference(
      AUTO_TRANSLATE_VISIBLE_STORAGE_KEY,
      value ? 'true' : 'false',
      'autoTranslateVisibleMessages',
    );

    if (value) {
      manuallyTranslatedConversationId.value = null;
    }
  });

  watch(
    [translationSourceLocale, translationTargetLocale],
    ([source, target]) => {
      writeLocalPreference(
        TRANSLATION_SOURCE_LOCALE_STORAGE_KEY,
        source,
        'translationSourceLocale',
      );
      writeLocalPreference(
        TRANSLATION_TARGET_LOCALE_STORAGE_KEY,
        target,
        'translationTargetLocale',
      );
    },
  );

  watch(autoTranslateReply, (value) => {
    writeLocalPreference(
      AUTO_TRANSLATE_REPLY_STORAGE_KEY,
      value ? 'true' : 'false',
      'autoTranslateReply',
    );
  });

  watch(
    () => selection.value?.conversation.id,
    () => {
      manuallyTranslatedConversationId.value = null;
    },
  );

  onMounted(loadStoredPreferences);

  return {
    showTimelineEvents,
    autoTranslateVisibleMessages,
    autoTranslateReply,
    translationSourceLocale,
    translationTargetLocale,
    translationPopoverOpen,
    translationEnabled,
    translationConversationScopeId,
    translationSourceOptions,
    replyAutoTranslateToggleTitle,
    toggleTimelineEvents,
    toggleReplyAutoTranslate,
    translateCurrentConversation,
  };
}
