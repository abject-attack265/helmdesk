/**
 * 文件说明：把当前激活的 tab 与 URL query 参数双向同步。
 */
import { onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue';

interface UseUrlTabOptions<T extends string> {
  defaultValue: T;
  valid: readonly T[];
}

const writeToUrl = (paramName: string, value: string | null): void => {
  if (typeof window === 'undefined') {
    return;
  }

  const url = new URL(window.location.href);

  if (value === null) {
    url.searchParams.delete(paramName);
  } else {
    url.searchParams.set(paramName, value);
  }

  window.history.replaceState(window.history.state, '', url.toString());
};

/**
 * 把标签页状态同步到当前 URL 查询参数，刷新页面或复制链接后仍能回到同一标签。
 *
 * 这里用 `history.replaceState` 静默更新地址，避免触发 Inertia 跳转；回到默认值或组件卸载时会清掉参数。
 */
export function useUrlTab<T extends string>(
  paramName: string,
  options: UseUrlTabOptions<T>,
): Ref<T> {
  const isValid = (value: string | null): value is T =>
    value !== null && (options.valid as readonly string[]).includes(value);

  const readFromUrl = (): T => {
    if (typeof window === 'undefined') {
      return options.defaultValue;
    }

    const raw = new URLSearchParams(window.location.search).get(paramName);

    return isValid(raw) ? raw : options.defaultValue;
  };

  const tab = ref<T>(readFromUrl()) as Ref<T>;

  onMounted(() => {
    writeToUrl(
      paramName,
      tab.value === options.defaultValue ? null : tab.value,
    );
  });

  onBeforeUnmount(() => {
    writeToUrl(paramName, null);
  });

  watch(tab, (next) => {
    writeToUrl(paramName, next === options.defaultValue ? null : next);
  });

  return tab;
}
