import { onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

export function updateTheme(value: Appearance) {
  if (typeof window === 'undefined') {
    return;
  }

  if (value === 'system') {
    const mediaQueryList = window.matchMedia('(prefers-color-scheme: dark)');
    const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

    document.documentElement.classList.toggle('dark', systemTheme === 'dark');
  } else {
    document.documentElement.classList.toggle('dark', value === 'dark');
  }
}

const setCookie = (name: string, value: string, days = 365) => {
  if (typeof document === 'undefined') {
    return;
  }

  const maxAge = days * 24 * 60 * 60;

  document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
  if (typeof window === 'undefined') {
    return null;
  }

  return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
  if (typeof window === 'undefined') {
    return null;
  }

  return localStorage.getItem('appearance') as Appearance | null;
};

const handleSystemThemeChange = () => {
  const currentAppearance = getStoredAppearance();

  updateTheme(currentAppearance || 'system');
};

export function initializeTheme() {
  if (typeof window === 'undefined') {
    return;
  }

  // 优先使用已保存的外观设置，没有设置时跟随系统。
  const savedAppearance = getStoredAppearance();
  updateTheme(savedAppearance || 'system');

  // 监听系统主题变化，保证“跟随系统”能实时生效。
  mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('system');

export function useAppearance() {
  onMounted(() => {
    const savedAppearance = localStorage.getItem(
      'appearance',
    ) as Appearance | null;

    if (savedAppearance) {
      appearance.value = savedAppearance;
    }
  });

  function updateAppearance(value: Appearance) {
    appearance.value = value;

    // 保存到本地，供浏览器端下次打开时读取。
    localStorage.setItem('appearance', value);

    // 同步到 cookie，供 SSR 首屏判断主题。
    setCookie('appearance', value);

    updateTheme(value);
  }

  return {
    appearance,
    updateAppearance,
  };
}
