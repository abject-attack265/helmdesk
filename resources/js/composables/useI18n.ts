/**
 * 文件说明：提供客户端语言偏好管理与按 Vue 应用实例隔离的 SSR 翻译状态。
 */
import { defaultLocale, locales, type Locale, type Messages } from '@/locales';
import { inject, ref, type App, type InjectionKey, type Ref } from 'vue';

interface I18nState {
  locale: Ref<Locale>;
  messages: Ref<Messages>;
}

const i18nStateKey: InjectionKey<I18nState> = Symbol('helmdesk-i18n');

const isLocale = (value: unknown): value is Locale =>
  typeof value === 'string' && Object.hasOwn(locales, value);

/**
 * 校验服务端下发的规范化语言值。
 */
export function requireLocale(value: unknown, source = 'locale'): Locale {
  if (!isLocale(value)) {
    throw new Error(
      `${source} must be one of: ${Object.keys(locales).join(', ')}`,
    );
  }

  return value;
}

const normalizeLocale = (value: unknown): Locale => {
  if (typeof value === 'string') {
    const normalized = value.replace('_', '-').toLowerCase();

    if (normalized === 'zh' || normalized.startsWith('zh-')) {
      return 'zh-CN';
    }
  }

  // 产品仅提供中文和英文，其他语言使用英文。
  return defaultLocale;
};

const setCookie = (name: string, value: string, days = 365) => {
  const maxAge = days * 24 * 60 * 60;

  document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredLocale = (): Locale | null => {
  if (typeof window === 'undefined') {
    return null;
  }

  const stored = localStorage.getItem('locale');

  if (!stored) {
    return null;
  }

  if (isLocale(stored)) {
    return stored;
  }

  console.warn('忽略无法识别的浏览器语言偏好。', { locale: stored });
  localStorage.removeItem('locale');

  return null;
};

const getBrowserLocale = (): Locale => {
  if (typeof navigator === 'undefined') {
    return defaultLocale;
  }

  return normalizeLocale(
    [...(navigator.languages ?? []), navigator.language].find((value) =>
      Boolean(value?.trim()),
    ),
  );
};

const resolveInitialLocale = (): Locale =>
  getStoredLocale() ?? getBrowserLocale();

/**
 * 创建与单个 Vue 应用实例绑定的语言状态。
 */
export function createI18nState(initialLocale?: Locale): I18nState {
  const locale = ref<Locale>(initialLocale ?? resolveInitialLocale());

  return {
    locale,
    messages: ref<Messages>(locales[locale.value]),
  };
}

const clientI18nState = createI18nState();

const applyLocale = (
  state: I18nState,
  newLocale: Locale,
  options: { persist?: boolean } = {},
) => {
  const messages = locales[newLocale];
  if (!messages) {
    throw new Error(`Unsupported locale: ${newLocale}`);
  }

  state.locale.value = newLocale;
  state.messages.value = messages;

  if (typeof document !== 'undefined') {
    document.documentElement.lang = newLocale;
  }

  if (options.persist === false || typeof window === 'undefined') {
    return;
  }

  localStorage.setItem('locale', newLocale);
  setCookie('locale', newLocale);
};

/**
 * 设置当前会话语言，不修改浏览器持久化偏好。
 */
export function forceSessionLocale(targetLocale: Locale) {
  applyLocale(clientI18nState, targetLocale, { persist: false });
}

/**
 * 将语言状态注入当前 Vue 应用；SSR 为每个请求传入独立状态。
 */
export function provideI18nState(
  app: App,
  state: I18nState = clientI18nState,
): void {
  app.provide(i18nStateKey, state);
}

export function useI18n() {
  const state = inject(i18nStateKey);

  if (!state) {
    throw new Error('I18n state has not been provided.');
  }

  const i18nState = state;

  function updateLocale(newLocale: Locale) {
    applyLocale(i18nState, newLocale);
  }

  /**
   * 使用中文 key 翻译，并替换 `{name}` 这类占位符。
   */
  function t(key: string, params?: Record<string, string | number>): string {
    let text =
      key in i18nState.messages.value
        ? (i18nState.messages.value[key as keyof Messages] as string)
        : key;

    if (params) {
      for (const [name, value] of Object.entries(params)) {
        text = text.replaceAll(`{${name}}`, String(value));
      }
    }

    return text;
  }

  return {
    locale: i18nState.locale,
    messages: i18nState.messages,
    updateLocale,
    t,
  };
}
